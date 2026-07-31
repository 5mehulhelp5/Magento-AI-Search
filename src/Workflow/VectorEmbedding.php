<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow;

use DavidBel\AiSearch\Api\EmbedderClientInterface;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingBatchFactory;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingExecution;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingExecutionFactory;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingInputMapper;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingPromisePool;
use Generator;
use Throwable;

class VectorEmbedding
{
    private const int BATCH_SIZE = 100;
    private const int CONCURRENT_REQUESTS = 3;
    private const int MAX_RUNTIME_SECONDS = 600;
    private const int NANOSECONDS_PER_SECOND = 1_000_000_000;

    public function __construct(
        private readonly EmbedderClientInterface $embedderClient,
        private readonly EmbeddingInputMapper $embeddingInputMapper,
        private readonly EmbeddingBatchFactory $embeddingBatchFactory,
        private readonly EmbeddingPromisePool $embeddingPromisePool,
        private readonly EmbeddingExecutionFactory $embeddingExecutionFactory
    ) {
    }

    public function execute(): int
    {
        $execution = $this->embeddingExecutionFactory->create();
        $execution->start();
        $this->runEmbeddingPool($execution);

        return $execution->finish();
    }

    private function runEmbeddingPool(EmbeddingExecution $execution): void
    {
        try {
            $this->embeddingPromisePool->run(
                $this->createEmbeddingPromises($execution),
                self::CONCURRENT_REQUESTS,
                [$execution, 'fulfilled'],
                [$execution, 'rejected']
            );
        } catch (Throwable $throwable) {
            $execution->stop($throwable);
        }
    }

    /**
     * @return Generator<int, \GuzzleHttp\Promise\PromiseInterface>
     */
    private function createEmbeddingPromises(EmbeddingExecution $execution): Generator
    {
        $cursorUpdatedAt = null;
        $cursorBacklogId = null;
        $batchId = 0;

        while ($execution->canAcceptWork(self::MAX_RUNTIME_SECONDS * self::NANOSECONDS_PER_SECOND)) {
            $rows = $execution->getPendingUpserts(
                self::BATCH_SIZE,
                $cursorUpdatedAt,
                $cursorBacklogId
            );

            if ($rows === []) {
                return;
            }

            $batch = $this->embeddingBatchFactory->create([
                'inputs' => $this->embeddingInputMapper->mapRows($rows),
            ]);
            $lastInput = $batch->getLastInput();
            $cursorUpdatedAt = $lastInput->backlogUpdatedAt;
            $cursorBacklogId = $lastInput->backlogId;
            $execution->addBatch($batchId, $batch);

            yield $batchId => $this->embedderClient->embedAsync(
                $batch->getContents()
            );

            $batchId++;
        }
    }
}
