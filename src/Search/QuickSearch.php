<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Search;

use DavidBel\AiSearch\Config\SearchResultConfig;
use DavidBel\AiSearch\Indexer\Versioning;
use Magento\Framework\Search\RequestInterface;

class QuickSearch
{
    public function __construct(
        private readonly RequestReader $requestReader,
        private readonly QueryEmbedding $queryEmbedding,
        private readonly VectorSearch $vectorSearch,
        private readonly CatalogQueryModifier $catalogQueryModifier,
        private readonly Versioning $versioning,
        private readonly SearchResultConfig $searchResultConfig
    ) {
    }

    /**
     * @param array<string, mixed> $catalogQuery
     * @return array<string, mixed>
     */
    public function execute(RequestInterface $request, array $catalogQuery): array
    {
        if (!$this->requestReader->isQuickSearch($request)) {
            return $catalogQuery;
        }

        $storeId = $this->requestReader->getStoreId($request);

        if (!$this->searchResultConfig->isEnabled($storeId)) {
            return $catalogQuery;
        }

        $queryText = $this->requestReader->getQueryText($request);

        if ($queryText === '') {
            return $catalogQuery;
        }

        $searchIndex = $this->versioning->getSearchIndex(
            $this->searchResultConfig->usePreviousSemanticIndexDuringRebuild($storeId)
        );

        if ($searchIndex === null) {
            return $catalogQuery;
        }

        $vector = $this->queryEmbedding->execute(
            $queryText,
            $storeId,
            $searchIndex->queryConfigurationSnapshot
        );
        $candidates = $this->vectorSearch->execute(
            $vector,
            $storeId,
            $searchIndex
        );

        return $this->catalogQueryModifier->execute($catalogQuery, $candidates);
    }
}
