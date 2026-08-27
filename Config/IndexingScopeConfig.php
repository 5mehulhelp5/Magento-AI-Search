<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use UnexpectedValueException;

class IndexingScopeConfig
{
    private const string XML_PATH_INDEXED_STORE_VIEWS =
        'davidbel_ai_search_semantic_search_source/indexing_scope/indexed_store_views';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @return list<int>
     */
    public function getStoreIdsForIndexing(): array
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_INDEXED_STORE_VIEWS);

        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Configuration path "%s" must contain a comma-separated list of store IDs.',
                    self::XML_PATH_INDEXED_STORE_VIEWS
                )
            );
        }

        $storeIds = [];

        foreach (explode(',', $value) as $configuredStoreId) {
            $storeId = filter_var(trim($configuredStoreId), FILTER_VALIDATE_INT);

            if ($storeId === false || $storeId < 1) {
                throw new UnexpectedValueException(
                    sprintf(
                        'Configuration path "%s" contains an invalid store ID.',
                        self::XML_PATH_INDEXED_STORE_VIEWS
                    )
                );
            }

            $storeIds[$storeId] = $storeId;
        }

        sort($storeIds, SORT_NUMERIC);

        return $storeIds;
    }
}
