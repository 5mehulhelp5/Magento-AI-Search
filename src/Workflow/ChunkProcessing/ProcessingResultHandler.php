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
use RuntimeException;
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
        private readonly ProcessingRun $processingRun
    ) {
    }

    /**
     * @param list<list<float>> $vectors
     */
    public function completed(array $vectors, int $batchId): void
    {
        try {
            $batch = $this->processingRun->getBatch($batchId);

            try {
                $result = $this->vectorSync->upsert($batch, $vectors);
            } catch (Throwable $throwable) {
                $this->openSearchFailed($batch->getBacklogIds(), $throwable);

                return;
            }

            $this->completeUpsert($result);
        } catch (Throwable $throwable) {
            $this->processingRun->stop($throwable);
        } finally {
            $this->processingRun->removeBatch($batchId);
        }
    }

    public function failed(mixed $reason, int $batchId): void
    {
        try {
            $this->getResource()->markFailedByIds(
                $this->processingRun->getBatch($batchId)->getBacklogIds(),
                self::EMBEDDER_ERROR_CATEGORY
            );
            $this->processingRun->stop($this->toThrowable($reason));
        } catch (Throwable $throwable) {
            $this->processingRun->stop($throwable);
        } finally {
            $this->processingRun->removeBatch($batchId);
        }
    }

    public function completeDeletion(Result $result): void
    {
        try {
            if ($this->handleVectorSyncResult($result)) {
                $this->processingRun->stop(
                    new RuntimeException('OpenSearch rejected one or more chunk deletions.')
                );
            }
        } catch (Throwable $throwable) {
            $this->processingRun->stop($throwable);
        }
    }

    /**
     * @param list<int> $backlogIds
     */
    public function openSearchFailed(array $backlogIds, Throwable $failure): void
    {
        try {
            $this->getResource()->markFailedByIds(
                $backlogIds,
                self::OPENSEARCH_ERROR_CATEGORY
            );
            $this->processingRun->stop($failure);
        } catch (Throwable $throwable) {
            $this->processingRun->stop($throwable);
        }
    }

    public function finish(): int
    {
        $successfulBacklogIds = $this->processingRun->getSuccessfulBacklogIds();

        try {
            $this->cacheClean->flush();
        } catch (Throwable $throwable) {
            $this->getResource()->markFailedByIds(
                $successfulBacklogIds,
                self::CACHE_ERROR_CATEGORY
            );
            $this->processingRun->stop($throwable);

            return $this->processingRun->getResult();
        }

        $this->getResource()->markDoneByIds($successfulBacklogIds);

        return $this->processingRun->getResult();
    }

    private function completeUpsert(Result $result): void
    {
        try {
            if ($this->handleVectorSyncResult($result)) {
                $this->processingRun->stop(
                    new RuntimeException('OpenSearch rejected one or more chunk documents.')
                );
            }
        } catch (Throwable $throwable) {
            $this->processingRun->stop($throwable);
        }
    }

    private function handleVectorSyncResult(Result $result): bool
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

        $this->processingRun->recordSuccesses($successfulBacklogIds);

        return $failedBacklogIds !== [];
    }

    private function getResource(): EmbeddingBacklogResource
    {
        $this->resource ??= $this->collectionFactory->create()->getResourceModel();

        return $this->resource;
    }

    private function toThrowable(mixed $reason): Throwable
    {
        if ($reason instanceof Throwable) {
            return $reason;
        }

        return new RuntimeException('The embedding request failed without an exception.');
    }
}
