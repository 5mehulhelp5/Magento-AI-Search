<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Plugin;

use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Config\SearchResultConfig;
use DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch\ResultProvider\SearchScores;
use DavidBel\AiSearch\Indexer\Versioning;
use DavidBel\AiSearch\Log\Logger;
use DavidBel\AiSearch\Observer\Config\InvalidateProductIndexer as ConfigObserver;
use DavidBel\AiSearch\Plugin\Adminhtml\Search\TestSemanticSearch\ChunkScoreCapture;
use DavidBel\AiSearch\Plugin\Adminhtml\Search\TestSemanticSearch\ProductScoreCapture;
use DavidBel\AiSearch\Plugin\Config\Importer\InvalidateProductIndexer as ImporterPlugin;
use DavidBel\AiSearch\Plugin\Config\ProcessorFacade\InvalidateProductIndexer as ProcessorPlugin;
use DavidBel\AiSearch\Plugin\GraphQl\SemanticSearchCacheIdentity;
use DavidBel\AiSearch\Plugin\OpenSearch\SemanticQuery;
use DavidBel\AiSearch\Search\Candidates;
use DavidBel\AiSearch\Search\QuickSearch;
use DavidBel\AiSearch\Search\ResultCache;
use DavidBel\AiSearch\Search\VectorSearch;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\CatalogGraphQl\Model\Resolver\Product\Identity;
use Magento\Config\Console\Command\ConfigSet\ProcessorFacade;
use Magento\Config\Model\Config\Importer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Search\RequestInterface;
use Magento\OpenSearch\SearchAdapter\Mapper;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PluginTest extends TestCase
{
    public function testSemanticQueryReturnsTheQuickSearchResult(): void
    {
        $request = self::createStub(RequestInterface::class);
        $quickSearch = $this->createMock(QuickSearch::class);
        $quickSearch->expects(self::once())
            ->method('execute')
            ->with($request, ['original' => true])
            ->willReturn(['semantic' => true]);
        $plugin = new SemanticQuery($quickSearch, self::createStub(Logger::class));

        self::assertSame(
            ['semantic' => true],
            $plugin->afterBuildQuery(
                self::createStub(Mapper::class),
                ['original' => true],
                $request
            )
        );
    }

    public function testSemanticQueryFallsBackAndLogsFailures(): void
    {
        $failure = new RuntimeException('failed');
        $quickSearch = self::createStub(QuickSearch::class);
        $quickSearch->method('execute')->willThrowException($failure);
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('semanticSearchFailed')->with($failure);
        $plugin = new SemanticQuery($quickSearch, $logger);

        self::assertSame(
            ['original' => true],
            $plugin->afterBuildQuery(
                self::createStub(Mapper::class),
                ['original' => true],
                self::createStub(RequestInterface::class)
            )
        );
    }

    public function testProcessesGraphQlSearchCacheIdentities(): void
    {
        $cache = $this->createMock(ResultCache::class);
        $cache->expects(self::once())
            ->method('process')
            ->with(['catalog_product_10'])
            ->willReturn(['processed']);
        $plugin = new SemanticSearchCacheIdentity($cache);

        self::assertSame(
            ['processed'],
            $plugin->afterGetIdentities(
                self::createStub(Identity::class),
                ['catalog_product_10'],
                ['layer_type' => Resolver::CATALOG_LAYER_SEARCH]
            )
        );
    }

    public function testLeavesNonSearchGraphQlCacheIdentitiesUnchanged(): void
    {
        $cache = $this->createMock(ResultCache::class);
        $cache->expects(self::never())->method('process');

        self::assertSame(
            ['original'],
            (new SemanticSearchCacheIdentity($cache))->afterGetIdentities(
                self::createStub(Identity::class),
                ['original'],
                []
            )
        );
    }

    public function testInvalidatesAfterConfigurationImport(): void
    {
        $versioning = $this->expectIndexerInvalidation();
        $result = (new ImporterPlugin($versioning))->afterImport(
            self::createStub(Importer::class),
            ['imported']
        );

        self::assertSame(['imported'], $result);
    }

    public function testInvalidatesAfterSearchSourceConfigurationChange(): void
    {
        $versioning = $this->expectIndexerInvalidation();
        $result = (new ProcessorPlugin($versioning))->afterProcessWithLockTarget(
            self::createStub(ProcessorFacade::class),
            'saved',
            'davidbel_ai_search_search_source/search_engine/vector_space'
        );

        self::assertSame('saved', $result);
    }

    public function testIgnoresAnUnrelatedConfigurationChange(): void
    {
        $versioning = $this->createMock(Versioning::class);
        $versioning->expects(self::never())->method('invalidateProductIndexerWhenNeeded');
        $result = (new ProcessorPlugin($versioning))->afterProcessWithLockTarget(
            self::createStub(ProcessorFacade::class),
            'saved',
            'web/secure/base_url'
        );

        self::assertSame('saved', $result);
    }

    public function testInvalidatesFromTheConfigurationObserver(): void
    {
        (new ConfigObserver($this->expectIndexerInvalidation()))
            ->execute(self::createStub(Observer::class));
    }

    public function testCapturesRelevantChunkScores(): void
    {
        $scores = new SearchScores();
        $config = self::createStub(SearchResultConfig::class);
        $config->method('getMinimumScore')->willReturn(0.5);
        $store = self::createStub(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $storeManager = self::createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $response = ['hits' => ['hits' => [
            ['_id' => '10', '_score' => 0.8],
            ['_id' => 20, '_score' => 0.4],
            ['_id' => 0, '_score' => 0.9],
            [],
        ]]];
        $plugin = new ChunkScoreCapture($scores, $config, $storeManager);

        self::assertSame($response, $plugin->afterSearch(self::createStub(OpenSearch::class), $response));
        self::assertSame([10 => 0.8], $scores->scoresByChunkId);
    }

    public function testCapturesProductScores(): void
    {
        $scores = new SearchScores();
        $candidates = new Candidates([10 => 0.8]);
        $result = (new ProductScoreCapture($scores))->afterExecute(
            self::createStub(VectorSearch::class),
            $candidates
        );

        self::assertSame($candidates, $result);
        self::assertSame([10 => 0.8], $scores->scoresByProductId);
    }

    private function expectIndexerInvalidation(): Versioning
    {
        $versioning = $this->createMock(Versioning::class);
        $versioning->expects(self::once())->method('invalidateProductIndexerWhenNeeded');

        return $versioning;
    }
}
