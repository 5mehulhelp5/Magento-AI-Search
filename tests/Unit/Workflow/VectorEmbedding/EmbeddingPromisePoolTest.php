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
    public function testDispatchesToCallableMethodsWithPromiseKeys(): void
    {
        $failure = new RuntimeException('request failed');
        $receiver = new class {
            /** @var array<int, mixed> */
            public array $fulfilled = [];

            /** @var array<int, mixed> */
            public array $rejected = [];

            public function fulfilled(mixed $value, int $key): void
            {
                $this->fulfilled[$key] = $value;
            }

            public function rejected(mixed $reason, int $key): void
            {
                $this->rejected[$key] = $reason;
            }
        };

        (new EmbeddingPromisePool())->run(
            [
                10 => Create::promiseFor(['vector']),
                20 => Create::rejectionFor($failure),
            ],
            2,
            [$receiver, 'fulfilled'],
            [$receiver, 'rejected']
        );

        self::assertSame([10 => ['vector']], $receiver->fulfilled);
        self::assertSame([20 => $failure], $receiver->rejected);
    }
}
