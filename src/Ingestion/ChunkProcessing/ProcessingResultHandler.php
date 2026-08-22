<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\IndexVersion
    as BacklogIndexVersion;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Result;
use Throwable;

class ProcessingResultHandler
{
    private const string EMBEDDER_ERROR_STAGE = 'embedder';
    private const string OPENSEARCH_ERROR_STAGE = 'opensearch';
    private const string CACHE_ERROR_STAGE = 'cache';

    private ?EmbeddingBacklogResource $resource = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly VectorSync $vectorSync,
        private readonly CacheClean $cacheClean,
        private readonly ProcessingState $processingState,
        private readonly BacklogIndexVersion $backlogIndexVersion
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
                $this->processingState->stopAcceptingWork();
                $this->openSearchFailed($batch->getBacklogVersions());

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
            $this->getResource()->markFailedByVersions(
                $this->processingState->getBatch($batchId)->getBacklogVersions(),
                self::EMBEDDER_ERROR_STAGE
            );
        } finally {
            $this->processingState->removeBatch($batchId);
        }
    }

    public function completeDelete(Result $result): void
    {
        $this->handleVectorSyncResult($result);
    }

    /**
     * @param array<int, int> $backlogVersions
     */
    public function openSearchFailed(array $backlogVersions): void
    {
        $this->getResource()->markFailedByVersions(
            $backlogVersions,
            self::OPENSEARCH_ERROR_STAGE
        );
    }

    public function finish(): int
    {
        $successfulBacklogVersions = $this->processingState->getSuccessfulBacklogVersions();

        try {
            $this->cacheClean->flush();
        } catch (Throwable $throwable) {
            $this->getResource()->markFailedByVersions(
                $successfulBacklogVersions,
                self::CACHE_ERROR_STAGE
            );

            throw $throwable;
        }

        $this->getResource()->markDoneByVersions($successfulBacklogVersions);

        return $this->processingState->getProcessedCount();
    }

    private function handleVectorSyncResult(Result $result): void
    {
        $failedBacklogVersions = $result->getFailedBacklogVersions();
        $successfulBacklogVersions = $result->getSuccessfulBacklogVersions();

        $this->backlogIndexVersion->markFullReindexItemsIndexed(
            $result->getSuccessfulBacklogIndexVersions()
        );

        $this->getResource()->markFailedByVersions(
            $failedBacklogVersions,
            self::OPENSEARCH_ERROR_STAGE
        );

        try {
            foreach ($result->getSuccessfulSourceEntities() as $entityType => $entityIds) {
                $this->cacheClean->register($entityType, $entityIds);
            }

            if ($successfulBacklogVersions !== []) {
                $this->cacheClean->registerSearchResults();
            }
        } catch (Throwable $throwable) {
            $this->getResource()->markFailedByVersions(
                $successfulBacklogVersions,
                self::CACHE_ERROR_STAGE
            );

            throw $throwable;
        }

        $this->processingState->recordSuccesses($successfulBacklogVersions);
    }

    private function getResource(): EmbeddingBacklogResource
    {
        $this->resource ??= $this->collectionFactory->create()->getResourceModel();

        return $this->resource;
    }
}
