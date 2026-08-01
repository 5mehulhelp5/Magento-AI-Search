<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Workflow\ChunkProcessing\CacheClean;
use DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingBatchFactory;
use DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingItemMapper;
use DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingResultHandler;
use DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingResultHandlerFactory;
use DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingRun;
use DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingRunFactory;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorEmbedding;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Delete\BatchFactory as DeleteBatchFactory;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Delete\ItemMapper as DeleteItemMapper;
use Generator;
use Throwable;

class ChunkProcessing
{
    private const int EMBEDDING_BATCH_SIZE = 100;
    private const int DELETION_BATCH_SIZE = 500;
    private const int CONCURRENT_EMBEDDING_REQUESTS = 3;
    private const int MAX_RUNTIME_SECONDS = 600;
    private const int NANOSECONDS_PER_SECOND = 1_000_000_000;

    private ?EmbeddingBacklogResource $resource = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly DeleteItemMapper $deleteItemMapper,
        private readonly DeleteBatchFactory $deleteBatchFactory,
        private readonly ProcessingItemMapper $processingItemMapper,
        private readonly ProcessingBatchFactory $processingBatchFactory,
        private readonly ProcessingRunFactory $processingRunFactory,
        private readonly ProcessingResultHandlerFactory $processingResultHandlerFactory,
        private readonly VectorEmbedding $vectorEmbedding,
        private readonly VectorSync $vectorSync,
        private readonly CacheClean $cacheClean
    ) {
    }

    public function execute(): int
    {
        $processingRun = $this->processingRunFactory->create();
        $resultHandler = $this->processingResultHandlerFactory->create([
            'processingRun' => $processingRun,
        ]);

        $this->cacheClean->start();
        $this->runDeletion($processingRun, $resultHandler);
        $this->runVectorEmbedding($processingRun, $resultHandler);

        return $this->finish($resultHandler);
    }

    private function runDeletion(
        ProcessingRun $processingRun,
        ProcessingResultHandler $resultHandler
    ): void {
        try {
            $rows = $this->getResource()->getItemsForDeletion(self::DELETION_BATCH_SIZE);

            if ($rows === []) {
                return;
            }

            $batch = $this->deleteBatchFactory->create([
                'items' => $this->deleteItemMapper->mapRows($rows),
            ]);
        } catch (Throwable $throwable) {
            $processingRun->stop($throwable);

            return;
        }

        try {
            $result = $this->vectorSync->delete($batch);
        } catch (Throwable $throwable) {
            $resultHandler->openSearchFailed($batch->getBacklogIds(), $throwable);

            return;
        }

        $resultHandler->completeDeletion($result);
    }

    private function runVectorEmbedding(
        ProcessingRun $processingRun,
        ProcessingResultHandler $resultHandler
    ): void {
        try {
            $this->vectorEmbedding->execute(
                $this->createProcessingBatches($processingRun),
                self::CONCURRENT_EMBEDDING_REQUESTS,
                [$resultHandler, 'completed'],
                [$resultHandler, 'failed']
            );
        } catch (Throwable $throwable) {
            $processingRun->stop($throwable);
        }
    }

    /**
     * @return Generator<int, \DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingBatch>
     */
    private function createProcessingBatches(ProcessingRun $processingRun): Generator
    {
        $cursorUpdatedAt = null;
        $cursorBacklogId = null;
        $batchId = 0;
        $maxRuntimeNanoseconds = self::MAX_RUNTIME_SECONDS * self::NANOSECONDS_PER_SECOND;

        while ($processingRun->canAcceptWork($maxRuntimeNanoseconds)) {
            $rows = $this->getResource()->getPendingUpsertsForEmbedding(
                self::EMBEDDING_BATCH_SIZE,
                $cursorUpdatedAt,
                $cursorBacklogId
            );

            if ($rows === []) {
                return;
            }

            $batch = $this->processingBatchFactory->create([
                'items' => $this->processingItemMapper->mapRows($rows),
            ]);
            $lastItem = $batch->getLastItem();
            $cursorUpdatedAt = $lastItem->backlogUpdatedAt;
            $cursorBacklogId = $lastItem->backlogId;
            $processingRun->addBatch($batchId, $batch);

            yield $batchId => $batch;

            $batchId++;
        }
    }

    private function finish(ProcessingResultHandler $resultHandler): int
    {
        return $resultHandler->finish();
    }

    private function getResource(): EmbeddingBacklogResource
    {
        $this->resource ??= $this->collectionFactory->create()->getResourceModel();

        return $this->resource;
    }
}
