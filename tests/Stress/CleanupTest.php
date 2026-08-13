<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress;

use DavidBel\AiSearch\Cron\ChunkDeletion;
use DavidBel\AiSearch\Indexer\ProductIndexer;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Tests\Stress\Support\CatalogDataset;
use DavidBel\AiSearch\Tests\Stress\Support\CronSchedule;
use DavidBel\AiSearch\Tests\Stress\Support\PipelineState;
use Magento\Framework\Indexer\IndexerRegistry;

class CleanupTest extends StressTestCase
{
    private const int MAXIMUM_RUNTIME_SECONDS = 3_600;

    public function testRemovesCatalogLocalAndRemoteStressData(): void
    {
        $dataset = $this->create(CatalogDataset::class);
        $pipelineState = $this->create(PipelineState::class);
        $productIds = $dataset->getAllProductIds();
        $parentIds = $dataset->getConfigurableProductIds();

        try {
            $dataset->removeCatalogData();

            if ($productIds !== []) {
                $this->get(IndexerRegistry::class)->get(ProductIndexer::ID)->reindexList($productIds);
                $pipelineState->markMissingChunkUpsertsOutdated();
                $this->processDeletions($pipelineState, $productIds);
            }

            self::assertSame(0, $pipelineState->getRemoteDocumentCount($parentIds));
            self::assertSame(0, $pipelineState->getDocumentCount($productIds));
            self::assertSame(0, $pipelineState->getChunkCount($productIds));
            self::assertSame(
                0,
                $pipelineState->getBacklogCount($productIds, Operation::Deletion, Status::Pending)
            );
            self::assertSame(
                0,
                $pipelineState->getBacklogCount($productIds, Operation::Deletion, Status::Failed)
            );
        } finally {
            $pipelineState->removeLocalData($productIds);
        }

        self::assertSame([], $dataset->getAllProductIds());
        self::assertSame(0, $pipelineState->getBacklogCount($productIds, Operation::Upsert));
        self::assertSame(0, $pipelineState->getBacklogCount($productIds, Operation::Deletion));
        $this->create(CronSchedule::class)->reset();
    }

    /**
     * @param list<int> $productIds
     */
    private function processDeletions(PipelineState $pipelineState, array $productIds): void
    {
        $chunkDeletion = $this->get(ChunkDeletion::class);
        $deadline = hrtime(true) + self::MAXIMUM_RUNTIME_SECONDS * 1_000_000_000;

        while ($pipelineState->getBacklogCount($productIds, Operation::Deletion, Status::Pending) > 0) {
            $chunkDeletion->execute();

            if (hrtime(true) < $deadline) {
                continue;
            }

            self::fail('Stress-data deletion did not finish within one hour.');
        }
    }
}
