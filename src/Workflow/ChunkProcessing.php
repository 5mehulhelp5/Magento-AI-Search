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
use DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingState;
use DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingStateFactory;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorEmbedding;
use Generator;

class ChunkProcessing
{
    private const int EMBEDDING_BATCH_SIZE = 100;
    private const int CONCURRENT_EMBEDDING_REQUESTS = 3;
    private const int MAX_RUNTIME_SECONDS = 600;
    private const int NANOSECONDS_PER_SECOND = 1_000_000_000;

    private ?EmbeddingBacklogResource $resource = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ProcessingItemMapper $processingItemMapper,
        private readonly ProcessingBatchFactory $processingBatchFactory,
        private readonly ProcessingStateFactory $processingStateFactory,
        private readonly ProcessingResultHandlerFactory $processingResultHandlerFactory,
        private readonly VectorEmbedding $vectorEmbedding,
        private readonly CacheClean $cacheClean
    ) {
    }

    public function execute(): int
    {
        $processingState = $this->processingStateFactory->create();
        $resultHandler = $this->processingResultHandlerFactory->create([
            'processingState' => $processingState,
        ]);

        $this->getResource()->markMissingChunkUpsertsOutdated();
        $this->cacheClean->start();
        $this->runVectorEmbedding($processingState, $resultHandler);

        return $this->finish($resultHandler);
    }

    private function runVectorEmbedding(
        ProcessingState $processingState,
        ProcessingResultHandler $resultHandler
    ): void {
        $this->vectorEmbedding->execute(
            $this->createProcessingBatches($processingState),
            self::CONCURRENT_EMBEDDING_REQUESTS,
            [$resultHandler, 'completed'],
            [$resultHandler, 'failed']
        );
    }

    /**
     * @return Generator<int, \DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingBatch>
     */
    private function createProcessingBatches(ProcessingState $processingState): Generator
    {
        $cursorUpdatedAt = null;
        $cursorBacklogId = null;
        $batchId = 0;
        $maxRuntimeNanoseconds = self::MAX_RUNTIME_SECONDS * self::NANOSECONDS_PER_SECOND;

        while ($processingState->isWithinRuntime($maxRuntimeNanoseconds)) {
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
            $processingState->addBatch($batchId, $batch);

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
