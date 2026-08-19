<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing;

use DavidBel\AiSearch\Search\ResultCache;
use InvalidArgumentException;
use Magento\Catalog\Model\Product;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Indexer\DeferredCacheCleanerInterface;

class CacheClean
{
    /**
     * @var array<string, string>
     */
    private readonly array $cacheTags;

    /**
     * @param array<string, string> $cacheTags
     */
    public function __construct(
        private readonly CacheContext $cacheContext,
        private readonly DeferredCacheCleanerInterface $cacheCleaner,
        array $cacheTags = []
    ) {
        $this->cacheTags = $cacheTags !== []
            ? $cacheTags
            : ['product' => Product::CACHE_TAG];
    }

    public function start(): void
    {
        $this->cacheCleaner->start();
    }

    /**
     * @param list<int> $entityIds
     */
    public function register(string $entityType, array $entityIds): void
    {
        if ($entityIds === []) {
            return;
        }

        $cacheTag = $this->cacheTags[$entityType] ?? null;

        if ($cacheTag === null) {
            throw new InvalidArgumentException(
                sprintf('Cache tag is not configured for source entity type %s.', $entityType)
            );
        }

        $this->cacheContext->registerEntities($cacheTag, $entityIds);
    }

    public function registerSearchResults(): void
    {
        $this->cacheContext->registerTags([ResultCache::CACHE_TAG]);
    }

    public function flush(): void
    {
        $this->cacheCleaner->flush();
    }
}
