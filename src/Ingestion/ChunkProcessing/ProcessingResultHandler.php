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
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingResultHandler\FailureReasonMapper;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Result;
use DavidBel\AiSearch\Model\EmbeddingBacklog\ErrorDetails;
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
        private readonly BacklogIndexVersion $backlogIndexVersion,
        private readonly FailureReasonMapper $failureReasonMapper
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
            } catch (Throwable $throwable) {
                $this->processingState->stopAcceptingWork();
                $this->openSearchFailed($batch->getBacklogVersions(), $throwable);

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
            $this->markBacklogItemsFailed(
                $this->processingState->getBatch($batchId)->getBacklogVersions(),
                self::EMBEDDER_ERROR_STAGE,
                $this->failureReasonMapper->map($reason)
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
    public function openSearchFailed(array $backlogVersions, mixed $reason): void
    {
        $this->markBacklogItemsFailed(
            $backlogVersions,
            self::OPENSEARCH_ERROR_STAGE,
            $this->failureReasonMapper->map($reason)
        );
    }

    public function finish(): int
    {
        $successfulBacklogVersions = $this->processingState->getSuccessfulBacklogVersions();

        try {
            $this->cacheClean->flush();
        } catch (Throwable $throwable) {
            $this->markBacklogItemsFailed(
                $successfulBacklogVersions,
                self::CACHE_ERROR_STAGE,
                $this->failureReasonMapper->map($throwable)
            );

            throw $throwable;
        }

        $this->getResource()->markDoneByVersions($successfulBacklogVersions);

        return $this->processingState->getProcessedCount();
    }

    private function handleVectorSyncResult(Result $result): void
    {
        $successfulBacklogVersions = $result->getSuccessfulBacklogVersions();

        $this->backlogIndexVersion->markFullReindexItemsIndexed(
            $result->getSuccessfulBacklogIndexVersions()
        );

        $this->markOpenSearchItemFailures($result->getFailedItems());

        try {
            foreach ($result->getSuccessfulSourceEntities() as $entityType => $entityIds) {
                $this->cacheClean->register($entityType, $entityIds);
            }

            if ($successfulBacklogVersions !== []) {
                $this->cacheClean->registerSearchResults();
            }
        } catch (Throwable $throwable) {
            $this->markBacklogItemsFailed(
                $successfulBacklogVersions,
                self::CACHE_ERROR_STAGE,
                $this->failureReasonMapper->map($throwable)
            );

            throw $throwable;
        }

        $this->processingState->recordSuccesses($successfulBacklogVersions);
    }

    /**
     * @param list<\DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\FailedItem> $failedItems
     */
    private function markOpenSearchItemFailures(array $failedItems): void
    {
        /**
         * @var array<string, array{
         *     error_details: \DavidBel\AiSearch\Model\EmbeddingBacklog\ErrorDetails,
         *     backlog_versions: array<int, int>
         * }> $failureGroups
         */
        $failureGroups = [];

        foreach ($failedItems as $failedItem) {
            $errorDetails = $failedItem->errorDetails;
            $groupKey = ($errorDetails->code ?? '') . "\0" . $errorDetails->message;

            if (!isset($failureGroups[$groupKey])) {
                $failureGroups[$groupKey] = [
                    'error_details' => $errorDetails,
                    'backlog_versions' => [],
                ];
            }

            $failureGroups[$groupKey]['backlog_versions'][$failedItem->item->backlogId]
                = $failedItem->item->backlogVersion;
        }

        foreach ($failureGroups as $failureGroup) {
            $this->markBacklogItemsFailed(
                $failureGroup['backlog_versions'],
                self::OPENSEARCH_ERROR_STAGE,
                $failureGroup['error_details']
            );
        }
    }

    /**
     * @param array<int, int> $backlogVersions
     */
    private function markBacklogItemsFailed(
        array $backlogVersions,
        string $errorStage,
        ErrorDetails $errorDetails
    ): void {
        $this->getResource()->markFailedByVersions(
            $backlogVersions,
            $errorStage,
            $errorDetails
        );
    }

    private function getResource(): EmbeddingBacklogResource
    {
        $this->resource ??= $this->collectionFactory->create()->getResourceModel();

        return $this->resource;
    }
}
