<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Yves\Application\Helper;

use Codeception\TestInterface;
use Spryker\Yves\Http\Plugin\Application\YvesHttpApplicationPlugin;
use SprykerTest\Shared\Application\Helper\AbstractApplicationHelper;

class ApplicationHelper extends AbstractApplicationHelper
{
    public function _before(TestInterface $test): void
    {
        parent::_before($test);

        $this->addApplicationPlugin(new YvesHttpApplicationPlugin());
    }
}
