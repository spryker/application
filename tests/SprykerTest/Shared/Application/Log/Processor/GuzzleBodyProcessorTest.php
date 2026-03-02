<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Shared\Application\Log\Processor;

use Codeception\Test\Unit;
use Spryker\Shared\Application\Log\Processor\GuzzleBodyProcessor;
use Spryker\Shared\Log\Sanitizer\Sanitizer;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Shared
 * @group Application
 * @group Log
 * @group Processor
 * @group GuzzleBodyProcessorTest
 * Add your own group annotations below this line
 */
class GuzzleBodyProcessorTest extends Unit
{
    public function testInvokeShouldAddGuzzleBodyToRecordsExtra(): void
    {
        $sanitizer = new Sanitizer([], '***');
        $processor = new GuzzleBodyProcessor($sanitizer);
        $record = ['extra' => [], 'context' => [GuzzleBodyProcessor::EXTRA => 'guzzle body string']];
        $result = $processor($record);

        $this->assertArrayHasKey(GuzzleBodyProcessor::EXTRA, $result['extra']);
    }

    public function testInvokeWithoutGuzzleBodyShouldNotAddToRecordsExtra(): void
    {
        $sanitizer = new Sanitizer([], '***');
        $processor = new GuzzleBodyProcessor($sanitizer);
        $record = ['extra' => [], 'context' => []];
        $result = $processor($record);

        $this->assertArrayNotHasKey(GuzzleBodyProcessor::EXTRA, $result['extra']);
    }

    /**
     * @dataProvider guzzleBodies
     *
     * @param mixed $body
     * @param array $expected
     *
     * @return void
     */
    public function testInvokeWithDifferentGuzzleBody($body, array $expected): void
    {
        $sanitizer = new Sanitizer(['replace'], '***');
        $processor = new GuzzleBodyProcessor($sanitizer);
        $record = ['extra' => [], 'context' => [GuzzleBodyProcessor::EXTRA => $body]];
        $result = $processor($record);

        $this->assertSame($expected, $result['extra'][GuzzleBodyProcessor::EXTRA]);
    }

    public function guzzleBodies(): array
    {
        return [
            ['string body', ['transfer-response' => 'string body']],
            ['{"json":"body"}', ['json' => 'body']],
            ['{"json":"body", "replace":"password"}', ['json' => 'body', 'replace' => '***']],
        ];
    }
}
