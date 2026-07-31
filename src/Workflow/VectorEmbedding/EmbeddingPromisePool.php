<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\VectorEmbedding;

use Closure;
use GuzzleHttp\Promise\EachPromise;

class EmbeddingPromisePool
{
    /**
     * @param iterable<int, \GuzzleHttp\Promise\PromiseInterface> $promises
     */
    public function run(
        iterable $promises,
        int $concurrency,
        Closure $fulfilled,
        Closure $rejected
    ): void {
        $pool = new EachPromise(
            $promises,
            [
                'concurrency' => $concurrency,
                'fulfilled' => $fulfilled,
                'rejected' => $rejected,
            ]
        );
        $pool->promise()->wait();
    }
}
