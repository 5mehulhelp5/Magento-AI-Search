<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\VectorEmbedding;

use Magento\Catalog\Model\Product;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Indexer\DeferredCacheCleanerInterface;

class ProductCacheCleaner
{
    public function __construct(
        private readonly CacheContext $cacheContext,
        private readonly DeferredCacheCleanerInterface $cacheCleaner
    ) {
    }

    public function start(): void
    {
        $this->cacheCleaner->start();
    }

    /**
     * @param list<int> $productIds
     */
    public function register(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        $this->cacheContext->registerEntities(Product::CACHE_TAG, $productIds);
    }

    public function flush(): void
    {
        $this->cacheCleaner->flush();
    }
}
