<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress;

use DavidBel\AiSearch\Cron\ChunkDeletion;
use DavidBel\AiSearch\Cron\ChunkProcessing;
use DavidBel\AiSearch\Cron\ChunkProcessingRetry;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Tests\Stress\Support\CatalogDataset;
use DavidBel\AiSearch\Tests\Stress\Support\PipelineState;

class TimelineTwoTest extends StressTestCase
{
    private const int MAXIMUM_RUNTIME_SECONDS = 1_200;

    public function testEmbedsAndPublishesEveryStressChunk(): void
    {
        $dataset = $this->create(CatalogDataset::class);
        $pipelineState = $this->create(PipelineState::class);
        $parentIds = $dataset->getConfigurableProductIds();

        self::assertCount(CatalogDataset::CONFIGURABLE_PRODUCT_COUNT, $parentIds);
        self::assertGreaterThan(0, $pipelineState->getChunkCount($parentIds));
        $this->processBacklog($pipelineState, $parentIds);

        self::assertSame(
            0,
            $pipelineState->getBacklogCount($parentIds, Operation::Upsert, Status::Pending)
        );
        self::assertSame(
            0,
            $pipelineState->getBacklogCount($parentIds, Operation::Upsert, Status::Failed)
        );
        self::assertSame(
            $pipelineState->getChunkCount($parentIds),
            $pipelineState->getBacklogCount($parentIds, Operation::Upsert, Status::Done)
        );
        self::assertTrue($pipelineState->hasWritableIndexForCurrentConfiguration());
        self::assertSame(
            $pipelineState->getChunkCount($parentIds),
            $pipelineState->getRemoteDocumentCount($parentIds)
        );
    }

    /**
     * @param list<int> $productIds
     */
    private function processBacklog(PipelineState $pipelineState, array $productIds): void
    {
        $chunkProcessing = $this->get(ChunkProcessing::class);
        $chunkDeletion = $this->get(ChunkDeletion::class);
        $chunkProcessingRetry = $this->get(ChunkProcessingRetry::class);
        $deadline = hrtime(true) + self::MAXIMUM_RUNTIME_SECONDS * 1_000_000_000;

        while ($this->getActionableBacklogCount($pipelineState, $productIds) > 0) {
            $chunkProcessingRetry->execute();
            $chunkProcessing->execute();
            $chunkDeletion->execute();

            if (hrtime(true) >= $deadline) {
                self::fail('Timeline two did not finish within twenty minutes.');
            }

            $pendingCount = $pipelineState->getBacklogCount(
                $productIds,
                Operation::Upsert,
                Status::Pending
            );
            $failedCount = $pipelineState->getBacklogCount(
                $productIds,
                Operation::Upsert,
                Status::Failed
            );

            if ($failedCount === 0 || $pendingCount > 0) {
                continue;
            }

            break;
        }

        self::assertSame(0, $this->getActionableBacklogCount($pipelineState, $productIds));
    }

    /**
     * @param list<int> $productIds
     */
    private function getActionableBacklogCount(PipelineState $pipelineState, array $productIds): int
    {
        return $pipelineState->getBacklogCount(
            $productIds,
            Operation::Upsert,
            Status::Pending
        ) + $pipelineState->getBacklogCount(
            $productIds,
            Operation::Upsert,
            Status::Failed
        );
    }
}
