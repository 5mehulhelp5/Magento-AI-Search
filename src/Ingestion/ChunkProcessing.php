<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion;

use DavidBel\AiSearch\Config\DataProcessingConfig;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\CacheClean;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingBatchFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingItemMapper;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingResultHandler;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingResultHandlerFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingState;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingStateFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorEmbedding;
use Generator;

class ChunkProcessing
{
    private const int NANOSECONDS_PER_SECOND = 1_000_000_000;

    private ?EmbeddingBacklogResource $resource = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ProcessingItemMapper $processingItemMapper,
        private readonly ProcessingBatchFactory $processingBatchFactory,
        private readonly ProcessingStateFactory $processingStateFactory,
        private readonly ProcessingResultHandlerFactory $processingResultHandlerFactory,
        private readonly VectorEmbedding $vectorEmbedding,
        private readonly CacheClean $cacheClean,
        private readonly DataProcessingConfig $dataProcessingConfig
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
            $this->dataProcessingConfig->getVectorEmbeddingConcurrentRequests(),
            [$resultHandler, 'completed'],
            [$resultHandler, 'failed']
        );
    }

    /**
     * @return Generator<int, \DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingBatch>
     */
    private function createProcessingBatches(ProcessingState $processingState): Generator
    {
        $cursorUpdatedAt = null;
        $cursorBacklogId = null;
        $batchId = 0;
        $batchSize = $this->dataProcessingConfig->getVectorEmbeddingBatchSize();
        $maxRuntimeNanoseconds = $this->dataProcessingConfig
            ->getVectorEmbeddingMaximumRuntimeSeconds() * self::NANOSECONDS_PER_SECOND;

        while ($processingState->isWithinRuntime($maxRuntimeNanoseconds)) {
            $rows = $this->getResource()->getPendingUpsertsForEmbedding(
                $batchSize,
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
