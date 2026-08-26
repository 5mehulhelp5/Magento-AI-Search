<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Search;

use DavidBel\AiSearch\Config\SemanticSearchResultConfig;
use DavidBel\AiSearch\Indexer\Versioning;
use RuntimeException;

class SemanticSearch
{
    public function __construct(
        private readonly QueryEmbedding $queryEmbedding,
        private readonly VectorSearch $vectorSearch,
        private readonly Versioning $versioning,
        private readonly SemanticSearchResultConfig $semanticSearchResultConfig
    ) {
    }

    public function getCandidates(string $queryText, int $storeId): Candidates
    {
        $searchIndex = $this->versioning->getSearchIndex(
            $this->semanticSearchResultConfig->usePreviousSemanticIndexDuringRebuild($storeId)
        );

        if ($searchIndex === null) {
            throw new RuntimeException('A semantic search index is not available.');
        }

        $vector = $this->queryEmbedding->execute(
            $queryText,
            $storeId,
            $searchIndex->queryConfigurationSnapshot
        );

        return $this->vectorSearch->execute($vector, $storeId, $searchIndex);
    }
}
