<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Workflow\VectorEmbedding;

use DavidBel\AiSearch\Workflow\VectorEmbedding\ProductCacheCleaner;
use Magento\Catalog\Model\Product;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Indexer\DeferredCacheCleanerInterface;
use PHPUnit\Framework\TestCase;

class ProductCacheCleanerTest extends TestCase
{
    public function testStartsRegistersProductsAndFlushesDeferredCleaning(): void
    {
        $cacheContext = $this->createMock(CacheContext::class);
        $cacheContext->expects(self::once())
            ->method('registerEntities')
            ->with(Product::CACHE_TAG, [10, 20])
            ->willReturnSelf();
        $deferredCleaner = $this->createMock(DeferredCacheCleanerInterface::class);
        $deferredCleaner->expects(self::once())
            ->method('start');
        $deferredCleaner->expects(self::once())
            ->method('flush');
        $cleaner = new ProductCacheCleaner($cacheContext, $deferredCleaner);

        $cleaner->start();
        $cleaner->register([10, 20]);
        $cleaner->flush();
    }

    public function testDoesNotRegisterAnEmptyProductSet(): void
    {
        $cacheContext = $this->createMock(CacheContext::class);
        $cacheContext->expects(self::never())
            ->method('registerEntities');

        (new ProductCacheCleaner(
            $cacheContext,
            self::createStub(DeferredCacheCleanerInterface::class)
        ))->register([]);
    }
}
