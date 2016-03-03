<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Application\Communication;

use Spryker\Shared\Application\ApplicationConstants;
use Spryker\Shared\Application\Business\Routing\SilexRouter;
use Spryker\Shared\Application\Communication\Application;
use Spryker\Shared\Config\Config;
use Spryker\Shared\Kernel\Store;
use Spryker\Shared\Library\DataDirectory;
use Spryker\Zed\Application\Business\Model\Router\MvcRouter;
use Spryker\Zed\Application\Communication\Plugin\Pimple;
use Spryker\Zed\Kernel\AbstractBundleDependencyProvider;
use Spryker\Zed\Kernel\BundleDependencyProviderResolverAwareTrait;
use Spryker\Zed\Kernel\Container;
use Spryker\Zed\Kernel\ControllerResolver\ZedFragmentControllerResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class ZedBootstrap
{

    use BundleDependencyProviderResolverAwareTrait;

    /**
     * @var \Spryker\Shared\Application\Communication\Application
     */
    protected $application;

    public function __construct()
    {
        $this->application = $this->getBaseApplication();
    }

    /**
     * @return \Spryker\Shared\Application\Communication\Application
     */
    public function boot()
    {
        $this->application['debug'] = Config::get(ApplicationConstants::ENABLE_APPLICATION_DEBUG, false);
        $this->application['locale'] = Store::getInstance()->getCurrentLocale();

        if (Config::get(ApplicationConstants::ENABLE_WEB_PROFILER, false)) {
            $this->application['profiler.cache_dir'] = DataDirectory::getLocalStoreSpecificPath('cache/profiler');
        }

        $this->optimizeApp();

        $this->registerServiceProvider();
        $this->registerRouters();

        $this->addVariablesToTwig();
        $this->addProtocolCheck();

        return $this->application;
    }

    /**
     * @return void
     */
    protected function registerRouters()
    {
        $this->application->addRouter(new MvcRouter($this->application));
        $this->application->addRouter(new SilexRouter($this->application));
    }

    /**
     * @return void
     */
    protected function registerServiceProvider()
    {
        foreach ($this->getServiceProvider() as $provider) {
            $this->application->register($provider);
        }
    }

    /**
     * @return \Silex\ServiceProviderInterface[]
     */
    protected function getServiceProvider()
    {
        return [];
    }

    /**
     * @return \Spryker\Shared\Application\Communication\Application
     */
    protected function getBaseApplication()
    {
        $application = new Application();

        $this->unsetSilexExceptionHandler($application);

        Pimple::setApplication($application);

        return $application;
    }

    /**
     * @param \Spryker\Shared\Application\Communication\Application $application
     *
     * @return void
     */
    private function unsetSilexExceptionHandler(Application $application)
    {
        unset($application['exception_handler']);
    }

    /**
     * @return void
     */
    protected function optimizeApp()
    {
        $application = $this->application;
        $application['resolver'] = $this->application->share(function () use ($application) {
            return new ZedFragmentControllerResolver($application, $application['logger']);
        });
    }

    /**
     * @return void
     */
    protected function addVariablesToTwig()
    {
        $application = $this->application;
        $application['twig.global.variables'] = $application->share(
            $application->extend('twig.global.variables', function (array $variables) use ($application) {
                $variables += [
                    'environment' => APPLICATION_ENV,
                    'store' => Store::getInstance()->getStoreName(),
                    'title' => Config::get(ApplicationConstants::PROJECT_NAMESPACE) . ' | Zed | ' . ucfirst(APPLICATION_ENV),
                    'currentController' => get_class($this),
                ];

                return $variables;
            })
        );
    }

    /**
     * @throws \Exception
     *
     * @return void
     */
    protected function addProtocolCheck()
    {
        if (!Config::get(ApplicationConstants::ZED_SSL_ENABLED)) {
            return;
        }
        $application = $this->application;
        $application->before(
            function (Request $request) {
                if (!$request->isSecure() && !in_array($request->getPathInfo(), Config::get(ApplicationConstants::ZED_SSL_EXCLUDED))) {
                    $fakeRequest = clone $request;
                    $fakeRequest->server->set('HTTPS', true);

                    return new RedirectResponse($fakeRequest->getUri(), 301);
                }

                return null;
            },
            255
        );
    }

    /**
     * @param \Spryker\Zed\Kernel\AbstractBundleDependencyProvider $dependencyProvider
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return void
     */
    protected function provideExternalDependencies(AbstractBundleDependencyProvider $dependencyProvider, Container $container)
    {
        $dependencyProvider->provideBusinessLayerDependencies($container);
        $dependencyProvider->provideCommunicationLayerDependencies($container);
        $dependencyProvider->providePersistenceLayerDependencies($container);
    }

}
