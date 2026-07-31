<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Workflow\VectorEmbedding;

use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingPromisePool;
use GuzzleHttp\Promise\Create;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EmbeddingPromisePoolTest extends TestCase
{
    public function testDispatchesFulfilledAndRejectedPromisesWithTheirKeys(): void
    {
        $failure = new RuntimeException('request failed');
        $fulfilled = [];
        $rejected = [];

        (new EmbeddingPromisePool())->run(
            [
                10 => Create::promiseFor(['vector']),
                20 => Create::rejectionFor($failure),
            ],
            2,
            static function (mixed $value, int $key) use (&$fulfilled): void {
                $fulfilled[$key] = $value;
            },
            static function (mixed $reason, int $key) use (&$rejected): void {
                $rejected[$key] = $reason;
            }
        );

        self::assertSame([10 => ['vector']], $fulfilled);
        self::assertSame([20 => $failure], $rejected);
    }
}
