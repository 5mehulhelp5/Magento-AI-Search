<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress;

use DavidBel\AiSearch\Indexer\ProductIndexer;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Tests\Stress\Support\CatalogDataset;
use DavidBel\AiSearch\Tests\Stress\Support\Measurement;
use DavidBel\AiSearch\Tests\Stress\Support\PipelineState;
use DavidBel\AiSearch\Tests\Stress\Support\StressConfiguration;
use Magento\Framework\Indexer\IndexerRegistry;

class TimelineOneTest extends StressTestCase
{
    public function testProcessesProductsIntoDocumentsChunksAndBacklog(): void
    {
        $startedAt = microtime(true);
        $dataset = $this->create(CatalogDataset::class);
        $configuration = $this->create(StressConfiguration::class);
        $pipelineState = $this->create(PipelineState::class);
        $parentIds = $dataset->getConfigurableProductIds();
        $childIds = $dataset->getSimpleProductIds();

        self::assertCount($configuration->getConfigurableProductCount(), $parentIds);
        self::assertCount(
            $configuration->getConfigurableProductCount()
                * $configuration->getSimpleProductsPerConfigurable(),
            $childIds
        );

        $indexer = $this->get(IndexerRegistry::class)->get(ProductIndexer::ID);
        $indexer->reindexAll();

        self::assertTrue($indexer->isValid());
        self::assertGreaterThanOrEqual(20, $pipelineState->getDocumentCount($parentIds));
        self::assertSame(0, $pipelineState->getDocumentCount($childIds));
        self::assertContains('description', $pipelineState->getSourceCodes($parentIds));
        self::assertContains('name', $pipelineState->getSourceCodes($parentIds));

        $descriptionChunkCount = $pipelineState->getChunkCount($parentIds, 'description');
        $totalChunkCount = $pipelineState->getChunkCount($parentIds);
        self::assertGreaterThan(count($parentIds), $descriptionChunkCount);
        self::assertGreaterThan($descriptionChunkCount, $totalChunkCount);
        self::assertSame(
            $totalChunkCount,
            $pipelineState->getBacklogCount($parentIds, Operation::Upsert)
        );
        $duration = microtime(true) - $startedAt;
        $this->create(Measurement::class)->recordStage('timeline_one', [
            'duration_seconds' => round($duration, 3),
            'stress_documents' => $pipelineState->getDocumentCount($parentIds),
            'stress_chunks' => $totalChunkCount,
            'stress_chunks_per_second' => round($totalChunkCount / $duration, 3),
            'all_documents' => $pipelineState->getAllDocumentCount(),
            'all_chunks' => $pipelineState->getAllChunkCount(),
            'all_backlog' => $pipelineState->getAllBacklogCount(),
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ]);
    }
}
