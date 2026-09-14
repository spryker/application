<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Application\Log\Processor;

/**
 * @deprecated Use `ResponseProcessorPlugin`s from Log module instead.
 */
class ResponseProcessor
{
    /**
     * @var string
     */
    public const EXTRA = 'response';

    /**
     * @var string
     */
    public const CONTEXT_KEY = 'response';

    /**
     * @var string
     */
    public const RECORD_CONTEXT = 'context';

    /**
     * @param \Monolog\LogRecord|array $record
     *
     * @return \Monolog\LogRecord|array
     */
    public function __invoke($record)
    {
        if (isset($record[static::RECORD_CONTEXT][static::EXTRA])) {
            unset($record[static::RECORD_CONTEXT][static::EXTRA]);
        }

        return $record;
    }
}
