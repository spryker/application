<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Yves\Application;

use Spryker\Shared\Application\EventListener\KernelLogListener;
use Spryker\Shared\Log\LoggerTrait;
use Spryker\Yves\Application\Expander\SecurityHeaderExpander;
use Spryker\Yves\Application\Expander\SecurityHeaderExpanderInterface;
use Spryker\Yves\Application\Plugin\Provider\ExceptionService\DefaultExceptionHandler;
use Spryker\Yves\Application\Plugin\Provider\ExceptionService\ExceptionHandlerDispatcher;
use Spryker\Yves\Kernel\AbstractFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * @method \Spryker\Yves\Application\ApplicationConfig getConfig()
 */
class ApplicationFactory extends AbstractFactory
{
    use LoggerTrait;

    /**
     * @return \Spryker\Yves\Application\Plugin\Provider\ExceptionService\ExceptionHandlerDispatcher
     */
    public function createExceptionHandlerDispatcher()
    {
        return new ExceptionHandlerDispatcher($this->createExceptionHandlers());
    }

    /**
     * @return array<\Spryker\Yves\Application\Plugin\Provider\ExceptionService\ExceptionHandlerInterface>
     */
    public function createExceptionHandlers()
    {
        return [
            Response::HTTP_NOT_FOUND => new DefaultExceptionHandler(),
        ];
    }

    /**
     * @return \Spryker\Shared\Application\EventListener\KernelLogListener
     */
    public function createKernelLogListener()
    {
        return new KernelLogListener($this->getLogger());
    }

    /**
     * @return \Spryker\Yves\Application\Expander\SecurityHeaderExpanderInterface
     */
    public function createSecurityHeaderExpander(): SecurityHeaderExpanderInterface
    {
        return new SecurityHeaderExpander(
            $this->getConfig(),
            $this->getSecurityHeaderExpanderPlugins(),
        );
    }

    /**
     * @return array<\Spryker\Yves\ApplicationExtension\Dependency\Plugin\SecurityHeaderExpanderPluginInterface>
     */
    public function getSecurityHeaderExpanderPlugins(): array
    {
        return $this->getProvidedDependency(ApplicationDependencyProvider::PLUGINS_SECURITY_HEADER_EXPANDER);
    }
}
