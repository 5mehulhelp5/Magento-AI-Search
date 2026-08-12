<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress;

use DavidBel\AiSearch\Indexer\ProductIndexer;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Tests\Stress\Support\CatalogDataset;
use DavidBel\AiSearch\Tests\Stress\Support\PipelineState;
use Magento\Framework\Indexer\IndexerRegistry;

class TimelineOneTest extends StressTestCase
{
    public function testProcessesProductsIntoDocumentsChunksAndBacklog(): void
    {
        $dataset = $this->create(CatalogDataset::class);
        $pipelineState = $this->create(PipelineState::class);
        $parentIds = $dataset->getConfigurableProductIds();
        $childIds = $dataset->getSimpleProductIds();

        self::assertCount(CatalogDataset::CONFIGURABLE_PRODUCT_COUNT, $parentIds);
        self::assertCount(100, $childIds);
        self::assertTrue(
            $pipelineState->hasWritableIndexForCurrentConfiguration(),
            'Run an initial full AI Search reindex before the local stress test.'
        );

        $indexer = $this->get(IndexerRegistry::class)->get(ProductIndexer::ID);
        $indexer->reindexList($dataset->getAllProductIds());

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
    }
}
