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
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete\Batch as DeleteBatch;
use DavidBel\AiSearch\Log\Logger;
use DavidBel\AiSearch\Model\EmbeddingBacklog\ErrorDetails;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
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
        private readonly FailureReasonMapper $failureReasonMapper,
        private readonly Logger $logger
    ) {
    }

    /**
     * @param list<list<float>> $vectors
     */
    public function completed(array $vectors, int $batchId): void
    {
        $batch = $this->processingState->getBatch($batchId);
        $affectedBacklogVersions = $batch->getBacklogVersions();
        $errorStage = self::OPENSEARCH_ERROR_STAGE;

        try {
            $result = $this->vectorSync->upsert($batch, $vectors);
            $successfulBacklogVersions = $result->getSuccessfulBacklogVersions();

            $this->recordVectorSyncResult(
                $result,
                Operation::Upsert,
                $batch->getIndexVersion(),
                $batchId
            );

            $affectedBacklogVersions = $successfulBacklogVersions;
            $errorStage = self::CACHE_ERROR_STAGE;
            $this->registerCacheInvalidation($result, $successfulBacklogVersions);
            $this->processingState->recordSuccesses($successfulBacklogVersions);
        } catch (Throwable $throwable) {
            $errorDetails = $this->failureReasonMapper->map($throwable);
            $this->processingState->stopAcceptingWork();
            $this->logger->batchFailed(
                Operation::Upsert,
                $errorStage,
                $batch->getIndexVersion(),
                $batchId,
                $batch->getSourceEntityIds(),
                array_keys($affectedBacklogVersions),
                $errorDetails,
                $throwable
            );
            $this->markBacklogItemsFailed(
                $affectedBacklogVersions,
                $errorStage,
                $errorDetails
            );
        } finally {
            $this->processingState->removeBatch($batchId);
        }
    }

    public function failed(mixed $reason, int $batchId): void
    {
        try {
            $batch = $this->processingState->getBatch($batchId);
            $errorDetails = $this->failureReasonMapper->map($reason);
            $this->logger->batchFailed(
                Operation::Upsert,
                self::EMBEDDER_ERROR_STAGE,
                $batch->getIndexVersion(),
                $batchId,
                $batch->getSourceEntityIds(),
                array_keys($batch->getBacklogVersions()),
                $errorDetails,
                $reason instanceof Throwable ? $reason : null
            );
            $this->markBacklogItemsFailed(
                $batch->getBacklogVersions(),
                self::EMBEDDER_ERROR_STAGE,
                $errorDetails
            );
        } finally {
            $this->processingState->removeBatch($batchId);
        }
    }

    public function completeDelete(
        Result $result,
        DeleteBatch $batch,
        int $batchId
    ): void {
        $successfulBacklogVersions = $result->getSuccessfulBacklogVersions();
        $affectedBacklogVersions = array_replace(
            $successfulBacklogVersions,
            $result->getFailedBacklogVersions()
        );
        $errorStage = self::OPENSEARCH_ERROR_STAGE;

        try {
            $this->recordVectorSyncResult(
                $result,
                Operation::Delete,
                $batch->getIndexVersion(),
                $batchId
            );

            $affectedBacklogVersions = $successfulBacklogVersions;
            $errorStage = self::CACHE_ERROR_STAGE;
            $this->registerCacheInvalidation($result, $successfulBacklogVersions);
            $this->processingState->recordSuccesses($successfulBacklogVersions);
        } catch (Throwable $throwable) {
            $errorDetails = $this->failureReasonMapper->map($throwable);
            $this->processingState->stopAcceptingWork();
            $this->logger->batchFailed(
                Operation::Delete,
                $errorStage,
                $batch->getIndexVersion(),
                $batchId,
                $batch->getSourceEntityIds(),
                array_keys($affectedBacklogVersions),
                $errorDetails,
                $throwable
            );
            $this->markBacklogItemsFailed(
                $affectedBacklogVersions,
                $errorStage,
                $errorDetails
            );
        }
    }

    public function openSearchFailed(
        DeleteBatch $batch,
        int $batchId,
        mixed $reason
    ): void {
        $errorDetails = $this->failureReasonMapper->map($reason);
        $this->logger->batchFailed(
            Operation::Delete,
            self::OPENSEARCH_ERROR_STAGE,
            $batch->getIndexVersion(),
            $batchId,
            $batch->getSourceEntityIds(),
            array_keys($batch->getBacklogVersions()),
            $errorDetails,
            $reason instanceof Throwable ? $reason : null
        );
        $this->markBacklogItemsFailed(
            $batch->getBacklogVersions(),
            self::OPENSEARCH_ERROR_STAGE,
            $errorDetails
        );
    }

    public function finish(): int
    {
        $successfulBacklogVersions = $this->processingState->getSuccessfulBacklogVersions();

        $this->getResource()->markDoneByVersions($successfulBacklogVersions);
        $this->cacheClean->flush();

        return $this->processingState->getProcessedCount();
    }

    private function recordVectorSyncResult(
        Result $result,
        Operation $operation,
        int $indexVersion,
        int $batchId
    ): void {
        $this->backlogIndexVersion->markFullReindexItemsIndexed(
            $result->getSuccessfulBacklogIndexVersions()
        );

        $this->markOpenSearchItemFailures(
            $result->getFailedItems(),
            $operation,
            $indexVersion,
            $batchId
        );
    }

    /**
     * @param array<int, int> $successfulBacklogVersions
     */
    private function registerCacheInvalidation(
        Result $result,
        array $successfulBacklogVersions
    ): void {
        foreach ($result->getSuccessfulSourceEntities() as $entityType => $entityIds) {
            $this->cacheClean->register($entityType, $entityIds);
        }

        if ($successfulBacklogVersions === []) {
            return;
        }

        $this->cacheClean->registerSearchResults();
    }

    /**
     * @param list<\DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\FailedItem> $failedItems
     */
    private function markOpenSearchItemFailures(
        array $failedItems,
        Operation $operation,
        int $indexVersion,
        int $batchId
    ): void {
        /**
         * @var array<string, array{
         *     error_details: \DavidBel\AiSearch\Model\EmbeddingBacklog\ErrorDetails,
         *     backlog_versions: array<int, int>,
         *     product_ids: array<int, true>
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
                    'product_ids' => [],
                ];
            }

            $failureGroups[$groupKey]['backlog_versions'][$failedItem->item->backlogId]
                = $failedItem->item->backlogVersion;
            $failureGroups[$groupKey]['product_ids'][$failedItem->item->sourceEntityId] = true;
        }

        foreach ($failureGroups as $failureGroup) {
            $this->logger->batchFailed(
                $operation,
                self::OPENSEARCH_ERROR_STAGE,
                $indexVersion,
                $batchId,
                array_keys($failureGroup['product_ids']),
                array_keys($failureGroup['backlog_versions']),
                $failureGroup['error_details']
            );
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
