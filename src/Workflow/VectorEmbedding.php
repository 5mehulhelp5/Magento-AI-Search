<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow;

use DavidBel\AiSearch\Api\EmbedderClientInterface;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingBatch;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingBatchFactory;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingInputMapper;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingPromisePool;
use Closure;
use Generator;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class VectorEmbedding
{
    private const int BATCH_SIZE = 100;
    private const int CONCURRENT_REQUESTS = 3;
    private const int MAX_RUNTIME_SECONDS = 600;
    private const int NANOSECONDS_PER_SECOND = 1_000_000_000;
    private const string EMBEDDER_ERROR_CATEGORY = 'embedder';

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly EmbedderClientInterface $embedderClient,
        private readonly EmbeddingInputMapper $embeddingInputMapper,
        private readonly EmbeddingBatchFactory $embeddingBatchFactory,
        private readonly EmbeddingPromisePool $embeddingPromisePool
    ) {
    }

    public function execute(): int
    {
        $resource = $this->collectionFactory->create()->getResourceModel();
        /** @var array<int, EmbeddingBatch> $batches */
        $batches = [];
        $acceptNewWork = true;
        $processedCount = 0;
        $failure = null;
        $startedAt = hrtime(true);
        $this->embeddingPromisePool->run(
            $this->createEmbeddingPromises(
                $resource,
                $batches,
                $acceptNewWork,
                $startedAt
            ),
            self::CONCURRENT_REQUESTS,
            $this->createFulfilledCallback(
                $resource,
                $batches,
                $processedCount
            ),
            $this->createRejectedCallback(
                $resource,
                $batches,
                $acceptNewWork,
                $failure
            )
        );

        if ($failure instanceof Throwable) {
            throw $failure;
        }

        return $processedCount;
    }

    /**
     * @param array<int, EmbeddingBatch> $batches
     * @return Generator<int, \GuzzleHttp\Promise\PromiseInterface>
     */
    private function createEmbeddingPromises(
        EmbeddingBacklogResource $resource,
        array &$batches,
        bool &$acceptNewWork,
        int $startedAt
    ): Generator {
        $cursorUpdatedAt = null;
        $cursorBacklogId = null;
        $batchId = 0;

        while ($acceptNewWork && !$this->hasReachedRuntimeLimit($startedAt)) {
            $rows = $resource->getPendingUpsertsForEmbedding(
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
            $batches[$batchId] = $batch;

            yield $batchId => $this->embedderClient->embedAsync(
                $batch->getContents()
            );

            $batchId++;
        }
    }

    /**
     * @param array<int, EmbeddingBatch> $batches
     */
    private function createFulfilledCallback(
        EmbeddingBacklogResource $resource,
        array &$batches,
        int &$processedCount
    ): Closure {
        return function (
            array $vectors,
            int $batchId
        ) use (
            $resource,
            &$batches,
            &$processedCount
        ): void {
            $batch = $this->getBatch($batches, $batchId);
            $resource->markEmbeddedByIds($batch->getBacklogIds());
            $processedCount += count($vectors);
            unset($batches[$batchId]);
        };
    }

    /**
     * @param array<int, EmbeddingBatch> $batches
     */
    private function createRejectedCallback(
        EmbeddingBacklogResource $resource,
        array &$batches,
        bool &$acceptNewWork,
        ?Throwable &$failure
    ): Closure {
        return function (
            mixed $reason,
            int $batchId
        ) use (
            $resource,
            &$batches,
            &$acceptNewWork,
            &$failure
        ): void {
            $batch = $this->getBatch($batches, $batchId);
            $resource->markFailedByIds(
                $batch->getBacklogIds(),
                self::EMBEDDER_ERROR_CATEGORY
            );
            $acceptNewWork = false;
            $failure ??= $this->toThrowable($reason);
            unset($batches[$batchId]);
        };
    }

    /**
     * @param array<int, EmbeddingBatch> $batches
     */
    private function getBatch(array $batches, int $batchId): EmbeddingBatch
    {
        if (!isset($batches[$batchId])) {
            throw new UnexpectedValueException('The completed embedding batch is unknown.');
        }

        return $batches[$batchId];
    }

    private function hasReachedRuntimeLimit(int $startedAt): bool
    {
        return hrtime(true) - $startedAt >= self::MAX_RUNTIME_SECONDS * self::NANOSECONDS_PER_SECOND;
    }

    private function toThrowable(mixed $reason): Throwable
    {
        if ($reason instanceof Throwable) {
            return $reason;
        }

        return new RuntimeException('The embedding request failed without an exception.');
    }
}
