<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing;

use DavidBel\AiSearch\Client\Embedding\Base\EmbedderClientPool;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorEmbedding\PromisePool;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorEmbedding\RequestBatchFactory;
use Generator;

class VectorEmbedding
{
    public function __construct(
        private readonly EmbedderClientPool $embedderClientPool,
        private readonly PromisePool $promisePool,
        private readonly RequestBatchFactory $requestBatchFactory
    ) {
    }

    /**
     * @param iterable<int, ProcessingBatch> $batches
     */
    public function execute(
        iterable $batches,
        int $concurrency,
        callable $completed,
        callable $failed
    ): void {
        $this->promisePool->run(
            $this->createPromises($batches),
            $concurrency,
            $completed,
            $failed
        );
    }

    /**
     * @param iterable<int, ProcessingBatch> $batches
     * @return Generator<int, \GuzzleHttp\Promise\PromiseInterface>
     */
    private function createPromises(iterable $batches): Generator
    {
        $client = $this->embedderClientPool->getClient();

        foreach ($batches as $batchId => $batch) {
            $requestBatch = $this->requestBatchFactory->create([
                'processingBatch' => $batch,
            ]);

            yield $batchId => $client
                ->embedDocumentsAsync($requestBatch->getUniqueInputs())
                ->then([$requestBatch, 'expandVectors']);
        }
    }
}
