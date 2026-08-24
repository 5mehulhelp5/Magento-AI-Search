<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Search;

use DavidBel\AiSearch\Config\SemanticSearchResultConfig;
use DavidBel\AiSearch\Search\ResultCache;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class ResultCacheTest extends TestCase
{
    public function testAppendsCacheTagWhenSemanticSearchIsEnabled(): void
    {
        $resultCache = $this->createResultCache(true, 3);

        self::assertSame(
            ['catalog_product_10', ResultCache::CACHE_TAG],
            $resultCache->process(['catalog_product_10'])
        );
    }

    public function testPreservesCacheTagsWhenSemanticSearchIsDisabled(): void
    {
        $resultCache = $this->createResultCache(false, 4);

        self::assertSame(
            ['catalog_product_10'],
            $resultCache->process(['catalog_product_10'])
        );
    }

    private function createResultCache(bool $enabled, int $storeId): ResultCache
    {
        $store = self::createStub(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);
        $storeManager = self::createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $semanticSearchResultConfig = $this->createMock(SemanticSearchResultConfig::class);
        $semanticSearchResultConfig->expects(self::once())
            ->method('isEnabled')
            ->with($storeId)
            ->willReturn($enabled);

        return new ResultCache($semanticSearchResultConfig, $storeManager);
    }
}
