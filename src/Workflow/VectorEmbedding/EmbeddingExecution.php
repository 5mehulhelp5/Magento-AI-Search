<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\VectorEmbedding;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class EmbeddingExecution
{
    private const string EMBEDDER_ERROR_CATEGORY = 'embedder';
    private const string CACHE_ERROR_CATEGORY = 'cache';
    private const string PRODUCT_ENTITY_TYPE = 'product';

    /**
     * @var array<int, EmbeddingBatch>
     */
    private array $batches = [];

    /**
     * @var list<int>
     */
    private array $successfulBacklogIds = [];

    private ?EmbeddingBacklogResource $resource = null;
    private bool $acceptNewWork = true;
    private int $processedCount = 0;
    private ?Throwable $failure = null;
    private readonly int $startedAt;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly OpenSearchUpdater $openSearchUpdater,
        private readonly ProductCacheCleaner $productCacheCleaner
    ) {
        $this->startedAt = hrtime(true);
    }

    public function start(): void
    {
        $this->productCacheCleaner->start();
    }

    public function canAcceptWork(int $maxRuntimeNanoseconds): bool
    {
        return $this->acceptNewWork
            && hrtime(true) - $this->startedAt < $maxRuntimeNanoseconds;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPendingUpserts(
        int $limit,
        ?string $cursorUpdatedAt,
        ?int $cursorBacklogId
    ): array {
        return $this->getResource()->getPendingUpsertsForEmbedding(
            $limit,
            $cursorUpdatedAt,
            $cursorBacklogId
        );
    }

    public function addBatch(int $batchId, EmbeddingBatch $batch): void
    {
        $this->batches[$batchId] = $batch;
    }

    /**
     * @param list<list<float>> $vectors
     */
    public function fulfilled(array $vectors, int $batchId): void
    {
        try {
            $result = $this->openSearchUpdater->update(
                $this->getResource(),
                $this->getBatch($batchId),
                $vectors
            );
            $this->productCacheCleaner->register(
                $result->getSuccessfulSourceEntityIds(self::PRODUCT_ENTITY_TYPE)
            );
            $this->successfulBacklogIds = array_merge(
                $this->successfulBacklogIds,
                $result->getSuccessfulBacklogIds()
            );
            $this->processedCount += $result->getSuccessfulCount();

            if ($result->getFailedBacklogIds() !== []) {
                throw new RuntimeException('OpenSearch rejected one or more chunk documents.');
            }
        } catch (Throwable $throwable) {
            $this->stop($throwable);
        } finally {
            unset($this->batches[$batchId]);
        }
    }

    public function rejected(mixed $reason, int $batchId): void
    {
        try {
            $this->getResource()->markFailedByIds(
                $this->getBatch($batchId)->getBacklogIds(),
                self::EMBEDDER_ERROR_CATEGORY
            );
            $this->stop($this->toThrowable($reason));
        } catch (Throwable $throwable) {
            $this->stop($throwable);
        } finally {
            unset($this->batches[$batchId]);
        }
    }

    public function stop(Throwable $failure): void
    {
        $this->acceptNewWork = false;
        $this->failure ??= $failure;
    }

    public function finish(): int
    {
        try {
            $this->productCacheCleaner->flush();
        } catch (Throwable $throwable) {
            $this->getResource()->markFailedByIds(
                $this->successfulBacklogIds,
                self::CACHE_ERROR_CATEGORY
            );
            $this->failure ??= $throwable;

            return $this->getResult();
        }

        $this->getResource()->markDoneByIds($this->successfulBacklogIds);

        return $this->getResult();
    }

    private function getResource(): EmbeddingBacklogResource
    {
        $this->resource ??= $this->collectionFactory->create()->getResourceModel();

        return $this->resource;
    }

    private function getBatch(int $batchId): EmbeddingBatch
    {
        if (!isset($this->batches[$batchId])) {
            throw new UnexpectedValueException('The completed embedding batch is unknown.');
        }

        return $this->batches[$batchId];
    }

    private function getResult(): int
    {
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        return $this->processedCount;
    }

    private function toThrowable(mixed $reason): Throwable
    {
        if ($reason instanceof Throwable) {
            return $reason;
        }

        return new RuntimeException('The embedding request failed without an exception.');
    }
}
