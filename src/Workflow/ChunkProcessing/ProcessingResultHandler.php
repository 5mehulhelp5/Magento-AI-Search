<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\ChunkProcessing;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Result;
use Throwable;

class ProcessingResultHandler
{
    private const string EMBEDDER_ERROR_CATEGORY = 'embedder';
    private const string OPENSEARCH_ERROR_CATEGORY = 'opensearch';
    private const string CACHE_ERROR_CATEGORY = 'cache';

    private ?EmbeddingBacklogResource $resource = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly VectorSync $vectorSync,
        private readonly CacheClean $cacheClean,
        private readonly ProcessingState $processingState
    ) {
    }

    /**
     * @param list<list<float>> $vectors
     */
    public function completed(array $vectors, int $batchId): void
    {
        try {
            $batch = $this->processingState->getBatch($batchId);

            try {
                $result = $this->vectorSync->upsert($batch, $vectors);
            } catch (Throwable) {
                $this->openSearchFailed($batch->getBacklogIds());

                return;
            }

            $this->handleVectorSyncResult($result);
        } finally {
            $this->processingState->removeBatch($batchId);
        }
    }

    public function failed(mixed $reason, int $batchId): void
    {
        try {
            $this->getResource()->markFailedByIds(
                $this->processingState->getBatch($batchId)->getBacklogIds(),
                self::EMBEDDER_ERROR_CATEGORY
            );
        } finally {
            $this->processingState->removeBatch($batchId);
        }
    }

    public function completeDeletion(Result $result): void
    {
        $this->handleVectorSyncResult($result);
    }

    /**
     * @param list<int> $backlogIds
     */
    public function openSearchFailed(array $backlogIds): void
    {
        $this->getResource()->markFailedByIds(
            $backlogIds,
            self::OPENSEARCH_ERROR_CATEGORY
        );
    }

    public function finish(): int
    {
        $successfulBacklogIds = $this->processingState->getSuccessfulBacklogIds();

        try {
            $this->cacheClean->flush();
        } catch (Throwable $throwable) {
            $this->getResource()->markFailedByIds(
                $successfulBacklogIds,
                self::CACHE_ERROR_CATEGORY
            );

            throw $throwable;
        }

        $this->getResource()->markDoneByIds($successfulBacklogIds);

        return $this->processingState->getProcessedCount();
    }

    private function handleVectorSyncResult(Result $result): void
    {
        $failedBacklogIds = $result->getFailedBacklogIds();
        $successfulBacklogIds = $result->getSuccessfulBacklogIds();

        $this->getResource()->markFailedByIds(
            $failedBacklogIds,
            self::OPENSEARCH_ERROR_CATEGORY
        );

        try {
            foreach ($result->getSuccessfulSourceEntities() as $entityType => $entityIds) {
                $this->cacheClean->register($entityType, $entityIds);
            }
        } catch (Throwable $throwable) {
            $this->getResource()->markFailedByIds(
                $successfulBacklogIds,
                self::CACHE_ERROR_CATEGORY
            );

            throw $throwable;
        }

        $this->processingState->recordSuccesses($successfulBacklogIds);
    }

    private function getResource(): EmbeddingBacklogResource
    {
        $this->resource ??= $this->collectionFactory->create()->getResourceModel();

        return $this->resource;
    }
}
