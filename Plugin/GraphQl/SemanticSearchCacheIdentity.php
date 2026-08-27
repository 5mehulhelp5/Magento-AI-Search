<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Plugin\GraphQl;

use DavidBel\AiSearch\Search\ResultCache;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\CatalogGraphQl\Model\Resolver\Product\Identity;

class SemanticSearchCacheIdentity
{
    public function __construct(
        private readonly ResultCache $resultCache
    ) {
    }

    /**
     * @param list<string> $identities
     * @param array<string, mixed> $resolvedData
     * @return list<string>
     */
    public function afterGetIdentities(
        Identity $subject,
        array $identities,
        array $resolvedData
    ): array {
        if (($resolvedData['layer_type'] ?? null) !== Resolver::CATALOG_LAYER_SEARCH) {
            return $identities;
        }

        /** @var list<string> $cacheIdentities */
        $cacheIdentities = $this->resultCache->process($identities);

        return $cacheIdentities;
    }
}
