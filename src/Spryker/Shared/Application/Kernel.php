<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types = 1);

namespace Spryker\Shared\Application;

use InvalidArgumentException;
use LogicException;
use Spryker\Service\Container\ContainerDelegator;
use Spryker\Service\Container\ContainerInterface;
use Spryker\Service\Container\Pass\BridgePass;
use Spryker\Service\Container\Pass\ProxyPass;
use Spryker\Service\Container\Pass\SprykerDefaultsPass;
use Spryker\Service\Container\Pass\StackResolverPass;
use Spryker\Shared\Application\DependencyInjection\HttpKernelPass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as SymfonyKernel;
use Throwable;

class Kernel extends SymfonyKernel
{
    use MicroKernelTrait;

    protected ?ApplicationInterface $application = null;

    /**
     * @var bool Flag to ensure symlink is created only once per request
     */
    private bool $isContainerSymlinkEnsured = false;

    protected ContainerInterface $applicationContainer;

    /**
     * This is called from the `\Spryker\Shared\Application\Application::handle()` to pass in the Spryker Container (ContainerProxy).
     *
     * The `\Spryker\Service\Container\ContainerInterface` that gets passed here is the Spryker Container, which will be filled
     * by all to the respective application attached `\Spryker\Shared\ApplicationExtension\Dependency\Plugin\ApplicationPluginInterface`s.
     *
     * @phpstan-param bool $debug
     */
    // phpcs:disable
    public function __construct(ContainerInterface $container, protected $debug = false)
    {// phpcs:enable
        /**
         * Make sure that this Kernel has a container that can be used later on.
         */
        $this->container = $container;

        /**
         * We make a copy of the passed Spryker Container (ContainerProxy) to have access to it later on.
         *
         * By this we achieve backward-compatibility for older projects partially migrating.
         */
        $this->applicationContainer = $container;

        /**
         * In case of a project updated this module (`spryker/application`), they might not have the `\Spryker\Service\Container\ContainerDelegator`.
         *
         * Return early in this case and assume the passed `\Spryker\Service\Container\ContainerInterface` is the only
         * container used in this application.
         */
        if (!class_exists(ContainerDelegator::class)) {
            return;
        }

        /**
         * For services applied by the `\Spryker\Shared\ApplicationExtension\Dependency\Plugin\ApplicationPluginInterface`s
         * we have to make them available inside the ContainerDelegator through the `application_container`.
         */
        $containerDelegator = ContainerDelegator::getInstance();
        $containerDelegator->attachContainer('application_container', $container);

        /**
         * When there is no `config/bundles.php` file, the container compilation will fail. When a project has only partially
         * updated but hasn't configured completely to use the Symfony Container, bootstrap the application would fail.
         *
         */
        if (!file_exists($this->getBundlesPath())) {
            return;
        }

        /**
         * We need to set `$this->container` to null so that the parent Kernel (Symfony Kernel)
         */
        $this->container = null;

        /**
         * For cases where we need to get the ContainerDelegator from the container, we make it globally available to the
         * passed `\Spryker\Service\Container\ContainerInterface` (ContainerProxy).
         */
        $container->set(ContainerDelegator::class, $containerDelegator);

        parent::__construct($this->getEnvironment(), $this->isDebug());
    }

    /**
     * We are registering the application here as we want during the request lifecycle to boot the ApplicationPlugins after
     * the project container was built.
     *
     * @see \Spryker\Shared\Application\Application::handle()
     */
    public function setApplication(ApplicationInterface $application): self
    {
        $this->application = $application;

        return $this;
    }

    /**
     * Symfony only allows env names that can also be used as class names. Spryker env's may contain a `.`. To prevent failing,
     * we have to return an environment name Symfony accepts.
     */
    public function getEnvironment(): string
    {
        return str_replace('.', '', APPLICATION_ENV);
    }

    public function isDebug(): bool
    {
        if ($this->container && method_exists($this->container, 'hasParameter') && method_exists($this->container, 'getParameter')) {
            return $this->container->hasParameter('debug') ? $this->container->getParameter('debug') : false;
        }

        return $this->debug;
    }

    public function boot(): void
    {
        /**
         * The application is marked "booted" after the container is compiled (only when not already compiled), and the
         * ApplicationPlugins are booted. To prevent this being done more than once, we return early.
         */
        if ($this->booted) {
            return;
        }

        /**
         * In case of a project updated this module (`spryker/application`), they might not have the `\Spryker\Service\Container\ContainerDelegator`.
         *
         * Return early in this case and assume the passed `\Spryker\Shared\ApplicationExtension\Dependency\Plugin\ApplicationPluginInterface`
         * is the only container used in this application.
         */
        if (!class_exists(ContainerDelegator::class)) {
            /**
             * The application will not be set within the ConsoleApplication that's why we need to check this here.
             *
             * Since we moved the boot process of the ApplicationPlugins to a later stage for "Containerized" applications,
             * we have to register and boot them now. Without this we would introduce a bc-breaking change.
             */
            if ($this->application && method_exists($this->application, 'registerPluginsAndBoot')) {
                $this->application->registerPluginsAndBoot($this->applicationContainer);
            }

            $this->booted = true;

            return;
        }

        /**
         * This calls the Symfony Kernel, which compiles the container (only when not already done), sets the compiled container
         * to all registered Symfony bundles, boots the bundles, and then marks the application as booted.
         *
         * Since the `\Symfony\Component\HttpKernel\Kernel::handle()` method already ensured `$this->container`, it is not
         * compiled a second time, and the boot method only registers and boots the Symfony Bundles.
         */
        parent::boot();

        /**
         * The container available in `$this->container` is the one that was compiled by Symfony and has a cache control around it.
         *
         * We are adding this "Symfony" container as `project_container` to the ContainerDelegator to be able to get services
         * from Symfony with the ContainerDelegator as well.
         *
         * We also make the ContainerDelegator the container used by the whole application.
         */
        $containerDelegator = ContainerDelegator::getInstance();
        $containerDelegator->attachContainer('project_container', $this->container);

        /**
         * At this point the `project_container` (Symfony Container) exists, and we are able to provide dependencies from the ApplicationPlugins.
         */
        if ($this->application && method_exists($this->application, 'registerPluginsAndBoot')) {
            $this->applicationContainer = $this->application->registerPluginsAndBoot($this->applicationContainer);
        }

        /**
         * We always attach the `$this->applicationContainer` to the ContainerDelegator.
         */
        $containerDelegator->attachContainer('application_container', $this->applicationContainer);

        $this->container = $containerDelegator;

        if ($this->application && method_exists($this->application, 'registerPluginsAndBoot')) {
            $this->application->setContainer($this->container);
        }

        /**
         * Now the application is fully booted, and the \Symfony\Component\HttpKernel\Kernel::handle() method continues
         * by getting the `\Symfony\Component\HttpKernel\HttpKernelInterface` from the container which is the ContainerDelegator.
         *
         * Since we do now have two `\Symfony\Component\HttpKernel\HttpKernelInterface` in the ContainerDelegator, we need to make sure
         * to use Spryker ones and not the one from Symfony.
         *
         * @see ContainerDelegator::get()
         */
    }

    private function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $configDir = $this->getConfigDir();

        $container->import($configDir . '/{packages}/*.{php,yaml}');
        $container->import($configDir . '/{packages}/' . $this->environment . '/*.{php,yaml}');

        if (is_file($configDir . '/services.yaml')) {
            $container->import($configDir . '/services.yaml');
            $container->import($configDir . '/{services}_' . $this->environment . '.yaml');

            return;
        }

        $container->import($configDir . '/{services,ApplicationServices,*Services}.php');
        $container->import($configDir . '/{services,ApplicationServices,*Services}_' . $this->environment . '.php');
    }

    /**
     * Gets the path to the configuration directory separated for each APPLICATION.
     *
     * We are using the constant APPLICATION which is set in the respective index.php files. Since we do not want to use the UPPERCASE name
     * in our config/ directory, we convert the APPLICATION constant to CamelCase.
     */
    private function getConfigDir(): string
    {
        return $this->getProjectDir() . '/config/' . $this->toCamelCase(APPLICATION);
    }

    private function toCamelCase(string $string): string
    {
        $applicationFragments = explode('_', $string);
        $applicationFragments = array_map(function ($fragment) {
            return ucfirst(strtolower($fragment));
        }, $applicationFragments);

        return implode('', $applicationFragments);
    }

    protected function getContainerClass(): string
    {
        $class = static::class . '_' . $this->toCamelCase(APPLICATION) . '_';
        $class = str_contains($class, "@anonymous\0") ? get_parent_class($class) . str_replace('.', '_', ContainerBuilder::hash($class)) : $class;
        $class = str_replace('\\', '_', $class) . ucfirst($this->environment) . ($this->debug ? 'Debug' : '') . 'Container';

        if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $class)) {
            throw new InvalidArgumentException(sprintf('The environment "%s" contains invalid characters, it can only contain characters allowed in PHP class names.', $this->environment));
        }

        return $class;
    }

    protected function build(ContainerBuilder $container): void
    {
        /**
         * When the ContainerDelegator does NOT exist or there is no `config/service.php`, we can skip adding our own Passes.
         *
         * This is done for container compilation performance when a project is only partially updated.
         */
        if (!class_exists(ContainerDelegator::class) || !file_exists($this->getConfigDir() . '/ApplicationServices.php')) {
            return;
        }

        $container
            // We need to pass the namespace aka organisation into the Pass to be able to skip using specific container in specific cases.
            // Check the description and implementation in the Pass itself
            ->addCompilerPass(new ProxyPass())
            ->addCompilerPass(new SprykerDefaultsPass())
            ->addCompilerPass(new BridgePass())
            ->addCompilerPass(new StackResolverPass())
            ->addCompilerPass(new HttpKernelPass());
    }

    protected function initializeBundles(): void
    {
        // BC: Return early to prevent applications not having a `config/bundles.php` file to crash.
        if (!file_exists($this->getBundlesPath())) {
            return;
        }

        $this->bundles = [];

        foreach ($this->registerBundles() as $bundle) {
            $name = $bundle->getName();
            if (isset($this->bundles[$name])) {
                throw new LogicException(sprintf('Trying to register two bundles with the same name "%s".', $name));
            }
            $this->bundles[$name] = $bundle;

            if ($bundle::class === FrameworkBundle::class) {
                /**
                 * Unset or disallow setting the 'kernel', 'http_kernel' and 'request_stack' services in the application container.
                 * If not unset, these services would override the ones defined in the Symfony FrameworkBundle.
                 * This is needed to have only one `request_stack` available in the application.
                 */
                $this->applicationContainer->remove('kernel');
                $this->applicationContainer->remove('http_kernel');
                $this->applicationContainer->remove('request_stack');
            }
        }
    }

    public function getProjectDir(): string
    {
        return APPLICATION_ROOT_DIR;
    }

    /**
     * Returns the cache directory path and ensures Container symlink exists if the cache directory is present.
     *
     * Note: This method has a side effect - it creates a symlink from 'Container'
     * to 'Container{hash}' if needed for Symfony cache:clear compatibility, but
     * only when the cache directory already exists.
     * The symlink creation is only performed once per request for performance.
     *
     * @return string
     */
    public function getCacheDir(): string
    {
        $cacheDir = $this->getProjectDir() . '/data/cache/' . $this->toCamelCase(APPLICATION) . '/' . $this->environment;

        if (!$this->isContainerSymlinkEnsured) {
            if (is_dir($cacheDir)) {
                $this->ensureContainerSymlink($cacheDir);
            }
            $this->isContainerSymlinkEnsured = true;
        }

        return $cacheDir;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/data/logs/' . $this->toCamelCase(APPLICATION) . '/';
    }

    /**
     * Creates symlink for Container directory to fix Symfony cache:clear issue.
     *
     * When Symfony PhpDumper compiles the container, it generates:
     * - A wrapper file: {cacheDir}/{ContainerClass}.php
     * - Actual container files: {cacheDir}/Container{hash}/{ContainerClass}.php
     *
     * However, Symfony\Bundle\FrameworkBundle\Command\CacheClearCommand uses reflection
     * on the container instance to find the directory:
     *   $containerFile = (new \ReflectionObject($kernel->getContainer()))->getFileName();
     *   $containerDir = basename(\dirname($containerFile));
     *
     * Since getFileName() returns the wrapper file path (one level up), basename(dirname())
     * returns the environment name instead of 'Container{hash}'.
     *
     * This creates a 'Container' symlink -> 'Container{hash}' to fix the mismatch.
     *
     * Note: If a 'Container' directory exists, no symlink is created. If a symlink exists,
     * it's validated to ensure it points to an existing directory. Stale symlinks are removed
     * and recreated to point to the current Container{hash} directory.
     */
    protected function ensureContainerSymlink(string $cacheDir): void
    {
        $containerSymlink = $cacheDir . '/Container';

        if (is_dir($containerSymlink) && !is_link($containerSymlink)) {
            return;
        }

        if (is_link($containerSymlink)) {
            $target = readlink($containerSymlink);

            // If symlink is valid (readlink succeeded and target exists), return early
            if ($target !== false && is_dir($cacheDir . '/' . $target)) {
                return;
            }

            if (unlink($containerSymlink) === false) {
                return;
            }
        }

        $containerDirs = glob($cacheDir . '/Container?*', GLOB_ONLYDIR);

        if ($containerDirs === false || $containerDirs === []) {
            return;
        }

        // Symfony creates one Container{hash} per compilation
        $hashDirName = basename(reset($containerDirs));

        try {
            symlink($hashDirName, $containerSymlink);
        } catch (Throwable $e) {
            return;
        }
    }
}
