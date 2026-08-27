<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Stress;

use DavidBel\AiSearch\Indexer\ProductIndexer;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Test\Stress\Support\BacklogScope;
use DavidBel\AiSearch\Test\Stress\Support\CatalogDataset;
use DavidBel\AiSearch\Test\Stress\Support\Measurement;
use DavidBel\AiSearch\Test\Stress\Support\PipelineState;
use DavidBel\AiSearch\Test\Stress\Support\StressConfiguration;
use Magento\Framework\Indexer\IndexerRegistry;

class TimelineOneTest extends StressTestCase
{
    public function testProcessesProductsIntoDocumentsChunksAndBacklog(): void
    {
        $startedAt = microtime(true);
        $dataset = $this->create(CatalogDataset::class);
        $configuration = $this->create(StressConfiguration::class);
        $pipelineState = $this->create(PipelineState::class);
        $searchableProductIds = $dataset->getSearchableProductIds();
        $nonSearchableProductIds = $configuration->usesStandaloneSimpleProducts()
            ? []
            : $dataset->getSimpleProductIds();

        $this->assertDatasetCounts(
            $configuration,
            $searchableProductIds,
            $nonSearchableProductIds
        );

        $indexer = $this->get(IndexerRegistry::class)->get(ProductIndexer::ID);
        $indexer->reindexAll();

        if ($configuration->usesStandaloneSimpleProducts()) {
            $this->create(BacklogScope::class)->keepOnlyProductIds($searchableProductIds);
        }

        self::assertTrue($indexer->isValid());
        $totalChunkCount = $this->assertProcessedState(
            $pipelineState,
            $searchableProductIds,
            $nonSearchableProductIds
        );
        $duration = microtime(true) - $startedAt;
        $this->create(Measurement::class)->recordStage('timeline_one', [
            'duration_seconds' => round($duration, 3),
            'stress_documents' => $pipelineState->getDocumentCount($searchableProductIds),
            'stress_chunks' => $totalChunkCount,
            'stress_chunks_per_second' => round($totalChunkCount / $duration, 3),
            'all_documents' => $pipelineState->getAllDocumentCount(),
            'all_chunks' => $pipelineState->getAllChunkCount(),
            'all_backlog' => $pipelineState->getAllBacklogCount(),
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ]);
    }

    /**
     * @param list<int> $searchableProductIds
     * @param list<int> $nonSearchableProductIds
     */
    private function assertDatasetCounts(
        StressConfiguration $configuration,
        array $searchableProductIds,
        array $nonSearchableProductIds
    ): void {
        self::assertCount(
            $configuration->usesStandaloneSimpleProducts()
                ? $configuration->getSimpleProductCount()
                : $configuration->getConfigurableProductCount(),
            $searchableProductIds
        );
        self::assertCount(
            $configuration->usesStandaloneSimpleProducts()
                ? 0
                : $configuration->getSimpleProductCount(),
            $nonSearchableProductIds
        );
    }

    /**
     * @param list<int> $searchableProductIds
     * @param list<int> $nonSearchableProductIds
     */
    private function assertProcessedState(
        PipelineState $pipelineState,
        array $searchableProductIds,
        array $nonSearchableProductIds
    ): int {
        self::assertGreaterThanOrEqual(
            count($searchableProductIds) * 2,
            $pipelineState->getDocumentCount($searchableProductIds)
        );
        self::assertSame(0, $pipelineState->getDocumentCount($nonSearchableProductIds));
        self::assertContains('description', $pipelineState->getSourceCodes($searchableProductIds));
        self::assertContains('name', $pipelineState->getSourceCodes($searchableProductIds));

        $descriptionChunkCount = $pipelineState->getChunkCount($searchableProductIds, 'description');
        $totalChunkCount = $pipelineState->getChunkCount($searchableProductIds);
        self::assertGreaterThan(count($searchableProductIds), $descriptionChunkCount);
        self::assertGreaterThan($descriptionChunkCount, $totalChunkCount);
        self::assertSame(
            $totalChunkCount,
            $pipelineState->getBacklogCount($searchableProductIds, Operation::Upsert)
        );

        return $totalChunkCount;
    }
}
