<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\ChunkProcessing\VectorEmbedding;

use GuzzleHttp\Promise\EachPromise;

class PromisePool
{
    /**
     * @param iterable<int, \GuzzleHttp\Promise\PromiseInterface> $promises
     */
    public function run(
        iterable $promises,
        int $concurrency,
        callable $completed,
        callable $failed
    ): void {
        $pool = new EachPromise(
            $promises,
            [
                'concurrency' => $concurrency,
                'fulfilled' => $completed,
                'rejected' => $failed,
            ]
        );
        $pool->promise()->wait();
    }
}
