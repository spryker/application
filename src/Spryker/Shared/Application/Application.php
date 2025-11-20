<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types = 1);

namespace Spryker\Shared\Application;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Spryker\Service\Container\Container;
use Spryker\Service\Container\ContainerInterface;
use Spryker\Shared\ApplicationExtension\Dependency\Plugin\ApplicationPluginInterface;
use Spryker\Shared\ApplicationExtension\Dependency\Plugin\BootableApplicationPluginInterface;
use Spryker\Shared\Kernel\Container\ContainerProxy;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\TerminableInterface;

class Application extends Container implements HttpKernelInterface, TerminableInterface, ApplicationInterface
{
    /**
     * @see \Symfony\Cmf\Component\Routing\ChainRouterInterface
     */
    public const string SERVICE_ROUTER = 'routers';

    /**
     * @see \Symfony\Component\HttpFoundation\Request
     */
    public const string SERVICE_REQUEST = 'request';

    /**
     * @see \Symfony\Component\HttpFoundation\RequestStack
     */
    public const string SERVICE_REQUEST_STACK = 'request_stack';

    /**
     * @see \Symfony\Component\EventDispatcher\EventDispatcherInterface
     *
     * @var string
     */
    protected const SERVICE_DISPATCHER = 'dispatcher';

    /**
     * @var array<\Spryker\Shared\ApplicationExtension\Dependency\Plugin\BootableApplicationPluginInterface>
     */
    protected array $bootablePlugins = [];

    /**
     * @var bool
     */
    protected bool $booted = false;

    /**
     * @var bool
     */
    protected bool $pluginsProvided = false;

    /**
     * @param array<\Spryker\Shared\ApplicationExtension\Dependency\Plugin\ApplicationPluginInterface> $applicationPlugins
     */
    public function __construct(protected ?PsrContainerInterface $container = null, protected array $applicationPlugins = [])
    {
        parent::__construct();

        if ($container === null) {
            $this->container = new Container();
        }

        /**
         * We have to provide the plugins for all applications that are not able to use the Symfony DependencyInjection yet.
         *
         * We will migrate the applications one after the other.
         */
        if (!$this->container->has('canUseDi') && $container instanceof ContainerProxy) {
            $this->registerPlugins();
        }

        $this->enableHttpMethodParameterOverride();
    }

    /**
     * After the application is booted and the project container is compiled as well as the ApplicationPlugins are booted,
     * we set back the ContainerDelegator as the container in the Application.
     *
     * This is needed to "park" the container and put it back into the Kernel later on.
     */
    public function setContainer(PsrContainerInterface $container): ApplicationInterface
    {
        $this->container = $container;

        return $this;
    }

    /**
     * This method is currently only used by the ConsoleApplication to pass in the Container into the Kernel that is used
     * in the Console.
     */
    public function getContainer(): PsrContainerInterface
    {
        return $this->container;
    }

    protected function registerPlugins(): void
    {
        foreach ($this->applicationPlugins as $applicationPlugin) {
            $this->registerApplicationPlugin($applicationPlugin);
        }

        $this->pluginsProvided = true;
    }

    /**
     * @param \Spryker\Shared\ApplicationExtension\Dependency\Plugin\ApplicationPluginInterface $applicationPlugin
     *
     * @return $this
     */
    public function registerApplicationPlugin(ApplicationPluginInterface $applicationPlugin)
    {
        $this->container = $applicationPlugin->provide($this->getApplicationContainer());

        if ($applicationPlugin instanceof BootableApplicationPluginInterface) {
            $this->bootablePlugins[] = $applicationPlugin;
        }

        return $this;
    }

    private function getApplicationContainer(): ContainerInterface
    {
        /** @phpstan-var \Spryker\Service\Container\ContainerInterface */
        return $this->container;
    }

    public function registerPluginsAndBoot(ContainerInterface $container): ContainerInterface
    {
        if ($this->booted) {
            return $container;
        }

        if (!$this->pluginsProvided) {
            $this->registerPlugins();
        }

        foreach ($this->bootablePlugins as $bootablePlugin) {
            $container = $bootablePlugin->boot($container);
        }

        return $container;
    }

    /**
     * @return $this
     */
    public function boot()
    {
        return $this;
    }

    /**
     * @return void
     */
    public function run(): void
    {
        $request = Request::createFromGlobals();

        $response = $this->handle($request);
        $response->send();
        $this->terminate($request, $response);
    }

    /**
     * @internal This method is called from the run() method and is for internal use only.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param int $type
     * @param bool $catch
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, int $type = self::MASTER_REQUEST, bool $catch = true): Response
    {
        /**
         * We check if we have a Kernel already in the container, this is true when the application was already started and
         * the Kernel was created the first time.
         *
         * When we don't have the Kernel in the container, we create one.
         *
         * In testing we come to the handle method more than once, that is why we need to make sure that we are not creating
         * the Kernel more often than needed.
         *
         * It is also required to store the kernel in the container because of setting the container in different ways at
         * different points in the application.
         */
        if (!$this->container->has('kernel')) {
            /**
             * We have to create a new instance of the Spryker Kernel and set the Spryker Container (ContainerProxy) as the container
             * to be used for setting up the application.
             */
            $kernel = new Kernel($this->getApplicationContainer(), $this->container->get('debug'));

            $this->getApplicationContainer()->set('kernel', $kernel);
            $this->getApplicationContainer()->set('request', $request);

            $kernel->setApplication($this);
        }

        /**
         * We are setting `$this` application to the Spryker Kernel to be able to access it later on. At this point NO
         * `\Spryker\Shared\ApplicationExtension\Dependency\Plugin\ApplicationPluginInterface`s is added.
         *
         * We need to do this that after Symfony Container MAY be compiled (done only when the system is configured to
         * use Symfony Container by having a `config/bundles.php` file as well as a service configuration file).
         *
         * The Symfony Kernel `handle` method creates the container (if not already booted, or the container is already created)
         * and calls the `boot` method which is overridden by the Spryker Kernel.
         *
         * @see \Spryker\Shared\Application\Kernel::boot()
         */
        return $this->getKernel()->handle($request);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param \Symfony\Component\HttpFoundation\Response $response
     *
     * @return void
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->getKernel()->terminate($request, $response);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param callable $controller
     *
     * @return void
     */
    public function dispatchControllerEvent(Request $request, callable $controller): void
    {
        $this->getDispatcher()->dispatch(
            new ControllerEvent($this->getKernel(), $controller, $request, HttpKernelInterface::MAIN_REQUEST),
            KernelEvents::CONTROLLER,
        );
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param \Symfony\Component\HttpFoundation\Response $response
     *
     * @return void
     */
    public function dispatchResponseEvent(Request $request, Response $response): void
    {
        $this->getDispatcher()->dispatch(
            new ResponseEvent($this->getKernel(), $request, HttpKernelInterface::MAIN_REQUEST, $response),
            KernelEvents::RESPONSE,
        );
    }

    /**
     * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected function getDispatcher(): EventDispatcherInterface
    {
        return $this->container->get('dispatcher');
    }

    /**
     * @return \Symfony\Component\HttpKernel\HttpKernel
     * @return \Spryker\Shared\Application\Kernel|\Symfony\Component\HttpKernel\HttpKernel
     */
    protected function getKernel()
    {
        // In case the current application doesn't know about the `kernel` we have to return the `http_kernel` as BC fallback.
        return $this->container->has('kernel') ? $this->container->get('kernel') : $this->container->get('http_kernel');
    }

    /**
     * Allow overriding http method. Needed to use the "_method" parameter in forms.
     * This should not be changeable by projects
     *
     * @return void
     */
    protected function enableHttpMethodParameterOverride(): void
    {
        Request::enableHttpMethodParameterOverride();
    }
}
