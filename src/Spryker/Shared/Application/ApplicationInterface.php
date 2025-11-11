<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Application;

use Psr\Container\ContainerInterface;

interface ApplicationInterface
{
    /**
     * @return $this
     */
    public function boot();

    /**
     * @return void
     */
    public function run(): void;

    public function setContainer(ContainerInterface $container): self;

    public function getContainer(): ContainerInterface;
}
