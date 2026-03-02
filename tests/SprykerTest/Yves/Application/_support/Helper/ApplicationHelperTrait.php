<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Yves\Application\Helper;

use Codeception\Module;

trait ApplicationHelperTrait
{
    protected function getApplicationHelper(): ApplicationHelper
    {
        /** @var \SprykerTest\Yves\Application\Helper\ApplicationHelper $applicationHelper */
        $applicationHelper = $this->getModule('\\' . ApplicationHelper::class);

        return $applicationHelper;
    }

    abstract protected function getModule(string $name): Module;
}
