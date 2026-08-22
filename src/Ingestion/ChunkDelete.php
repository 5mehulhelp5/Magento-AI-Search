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
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingResultHandler;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingResultHandlerFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingState;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingStateFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete\BatchFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete\ItemMapper;
use Generator;
use Throwable;

class ChunkDelete
{
    private const int NANOSECONDS_PER_SECOND = 1_000_000_000;

    private ?EmbeddingBacklogResource $resource = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ItemMapper $itemMapper,
        private readonly BatchFactory $batchFactory,
        private readonly ProcessingStateFactory $processingStateFactory,
        private readonly ProcessingResultHandlerFactory $processingResultHandlerFactory,
        private readonly VectorSync $vectorSync,
        private readonly CacheClean $cacheClean,
        private readonly DataProcessingConfig $dataProcessingConfig
    ) {
    }

    public function execute(int $indexVersion): int
    {
        $processingState = $this->processingStateFactory->create();
        $resultHandler = $this->processingResultHandlerFactory->create([
            'processingState' => $processingState,
        ]);

        $this->cacheClean->start();
        $this->run($processingState, $resultHandler, $indexVersion);

        return $resultHandler->finish();
    }

    private function run(
        ProcessingState $processingState,
        ProcessingResultHandler $resultHandler,
        int $indexVersion
    ): void {
        foreach ($this->createBatches($processingState, $indexVersion) as $batch) {
            try {
                $result = $this->vectorSync->delete($batch);
            } catch (Throwable $throwable) {
                $resultHandler->openSearchFailed(
                    $batch->getBacklogVersions(),
                    $throwable
                );

                break;
            }

            $resultHandler->completeDelete($result);
        }
    }

    /**
     * @return Generator<int, \DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete\Batch>
     */
    private function createBatches(
        ProcessingState $processingState,
        int $indexVersion
    ): Generator {
        $cursorUpdatedAt = null;
        $cursorBacklogId = null;
        $batchSize = $this->dataProcessingConfig->getVectorDeleteBatchSize();
        $upsertAttemptThreshold = $this->dataProcessingConfig
            ->getVectorDeleteUpsertAttemptThreshold();
        $maxRuntimeNanoseconds = $this->dataProcessingConfig
            ->getVectorDeleteMaximumRuntimeSeconds() * self::NANOSECONDS_PER_SECOND;

        while ($processingState->isWithinRuntime($maxRuntimeNanoseconds)) {
            $rows = $this->getResource()->getItemsToDelete(
                $indexVersion,
                $batchSize,
                $upsertAttemptThreshold,
                $cursorUpdatedAt,
                $cursorBacklogId
            );

            if ($rows === []) {
                return;
            }

            $batch = $this->batchFactory->create([
                'items' => $this->itemMapper->mapRows($rows),
            ]);
            $lastItem = $batch->getLastItem();
            $cursorUpdatedAt = $lastItem->backlogUpdatedAt;
            $cursorBacklogId = $lastItem->backlogId;

            yield $batch;
        }
    }

    private function getResource(): EmbeddingBacklogResource
    {
        $this->resource ??= $this->collectionFactory->create()->getResourceModel();

        return $this->resource;
    }
}
