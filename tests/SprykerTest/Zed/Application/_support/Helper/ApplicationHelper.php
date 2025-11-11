<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Application\Helper;

use Codeception\TestInterface;
use Spryker\Service\Container\ContainerInterface;
use Spryker\Shared\Application\Kernel;
use Spryker\Shared\ApplicationExtension\Dependency\Plugin\ApplicationPluginInterface;
use Spryker\Zed\Http\Communication\Plugin\Application\HttpApplicationPlugin;
use SprykerTest\Shared\Application\Helper\AbstractApplicationHelper;

class ApplicationHelper extends AbstractApplicationHelper
{
    /**
     * @param \Codeception\TestInterface $test
     *
     * @return void
     */
    public function _before(TestInterface $test): void
    {
        parent::_before($test);

        $this->addApplicationPlugin(new HttpApplicationPlugin());
        $this->addApplicationPlugin(new class implements ApplicationPluginInterface {
            public function provide(ContainerInterface $container): ContainerInterface
            {
                $container->set('kernel', function () use ($container) {
                    return new Kernel($container, 'test');
                });

                return $container;
            }
        });

        $this->getRequest()->server->set('SERVER_NAME', 'localhost');
    }
}
