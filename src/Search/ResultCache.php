<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Search;

use DavidBel\AiSearch\Config\SearchResultConfig;
use Magento\PageCache\Model\Spi\PageCacheTagsPreprocessorInterface;
use Magento\Store\Model\StoreManagerInterface;

class ResultCache implements PageCacheTagsPreprocessorInterface
{
    public const string CACHE_TAG = 'davidbel_ai_search_result';

    public function __construct(
        private readonly SearchResultConfig $searchResultConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param array<array-key, mixed> $tags
     * @return array<array-key, mixed>
     */
    public function process(array $tags): array
    {
        $storeId = (int) (string) $this->storeManager->getStore()->getId();

        if (!$this->searchResultConfig->isEnabled($storeId)) {
            return $tags;
        }

        $tags[] = self::CACHE_TAG;

        return $tags;
    }
}
