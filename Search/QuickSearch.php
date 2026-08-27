<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Search;

use DavidBel\AiSearch\Config\SemanticSearchResultConfig;
use Magento\Framework\Search\RequestInterface;

class QuickSearch
{
    public function __construct(
        private readonly RequestReader $requestReader,
        private readonly SemanticSearch $semanticSearch,
        private readonly CatalogQueryModifier $catalogQueryModifier,
        private readonly SemanticSearchResultConfig $semanticSearchResultConfig
    ) {
    }

    /**
     * @param array<string, mixed> $catalogQuery
     * @return array<string, mixed>
     */
    public function execute(RequestInterface $request, array $catalogQuery): array
    {
        if (!$this->requestReader->isSemanticSearchRequest($request)) {
            return $catalogQuery;
        }

        $storeId = $this->requestReader->getStoreId($request);

        if (!$this->semanticSearchResultConfig->isEnabled($storeId)) {
            return $catalogQuery;
        }

        $queryText = $this->requestReader->getQueryText($request);

        if ($queryText === '') {
            return $catalogQuery;
        }

        $candidates = $this->semanticSearch->getCandidates($queryText, $storeId);

        return $this->catalogQueryModifier->execute($catalogQuery, $candidates);
    }
}
