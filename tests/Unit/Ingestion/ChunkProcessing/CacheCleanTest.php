<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\ChunkProcessing;

use DavidBel\AiSearch\Ingestion\ChunkProcessing\CacheClean;
use InvalidArgumentException;
use Magento\Catalog\Model\Product;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Indexer\DeferredCacheCleanerInterface;
use PHPUnit\Framework\TestCase;

class CacheCleanTest extends TestCase
{
    public function testRunsDeferredCleaningAndRegistersProductEntities(): void
    {
        $context = $this->createMock(CacheContext::class);
        $context->expects(self::once())
            ->method('registerEntities')
            ->with(Product::CACHE_TAG, [10, 20]);
        $cleaner = $this->createMock(DeferredCacheCleanerInterface::class);
        $cleaner->expects(self::once())->method('start');
        $cleaner->expects(self::once())->method('flush');
        $cacheClean = new CacheClean($context, $cleaner);

        $cacheClean->start();
        $cacheClean->register('product', [10, 20]);
        $cacheClean->flush();
    }

    public function testIgnoresAnEmptyEntitySet(): void
    {
        $context = $this->createMock(CacheContext::class);
        $context->expects(self::never())->method('registerEntities');

        (new CacheClean(
            $context,
            self::createStub(DeferredCacheCleanerInterface::class)
        ))->register('unknown', []);
    }

    public function testRejectsAnUnconfiguredSourceEntityType(): void
    {
        $cacheClean = new CacheClean(
            self::createStub(CacheContext::class),
            self::createStub(DeferredCacheCleanerInterface::class)
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Cache tag is not configured for source entity type category.'
        );

        $cacheClean->register('category', [10]);
    }
}
