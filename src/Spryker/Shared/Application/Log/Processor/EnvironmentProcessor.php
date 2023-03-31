<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Application\Log\Processor;

use Spryker\Shared\Kernel\Store;

/**
 * @deprecated Use `EnvironmentProcessor` of Log module instead.
 */
class EnvironmentProcessor
{
    /**
     * @var string
     */
    public const EXTRA = 'environment';

    /**
     * @var string
     */
    public const APPLICATION = 'application';

    /**
     * @var string
     */
    public const ENVIRONMENT = 'environment';

    /**
     * @var string
     */
    public const STORE = 'store';

    /**
     * @var string
     */
    public const CODE_BUCKET = 'codeBucket';

    /**
     * @var string
     */
    public const LOCALE = 'locale';

    /**
     * @var string
     */
    public const RECORD_EXTRA = 'extra';

    /**
     * @param array $record
     *
     * @return array
     */
    public function __invoke(array $record)
    {
        $record[static::RECORD_EXTRA][static::EXTRA] = $this->getData();

        return $record;
    }

    /**
     * @return array
     */
    protected function getData()
    {
        $data = [
            static::APPLICATION => APPLICATION,
            static::ENVIRONMENT => APPLICATION_ENV,
            static::CODE_BUCKET => APPLICATION_CODE_BUCKET,
        ];

        if (!Store::isDynamicStoreMode()) {
            $store = $this->getStore();

            $data[static::STORE] = $store->getStoreName();
            $data[static::LOCALE] = $store->getCurrentLocale();
        }

        return $data;
    }

    /**
     * @return \Spryker\Shared\Kernel\Store
     */
    protected function getStore()
    {
        return Store::getInstance();
    }
}
