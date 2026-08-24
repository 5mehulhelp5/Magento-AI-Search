<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Search;

use DavidBel\AiSearch\Client\Embedding\Base\EmbedderClientPool;
use DavidBel\AiSearch\Config\SemanticSearchResultConfig;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex\QueryConfigurationSnapshot;
use UnexpectedValueException;

class QueryEmbedding
{
    public function __construct(
        private readonly EmbedderClientPool $embedderClientPool,
        private readonly SemanticSearchResultConfig $semanticSearchResultConfig
    ) {
    }

    /**
     * @return list<float>
     */
    public function execute(
        string $queryText,
        int $storeId,
        QueryConfigurationSnapshot $indexConfiguration
    ): array {
        $requestConfiguration = new QueryConfigurationSnapshot(
            $indexConfiguration->embeddingModel,
            $indexConfiguration->vectorDimensions,
            $this->semanticSearchResultConfig->getEmbedderQueryTemplate($storeId)
        );
        $vectors = $this->embedderClientPool
            ->getClient()
            ->embedQueryAsync(
                $queryText,
                $this->semanticSearchResultConfig->getRequestTimeoutSeconds($storeId),
                $requestConfiguration
            )
            ->wait();

        if (!is_array($vectors) || count($vectors) !== 1) {
            throw new UnexpectedValueException('Query embedding returned an unexpected vector count.');
        }

        $vector = reset($vectors);

        if (!is_array($vector) || !array_is_list($vector)) {
            throw new UnexpectedValueException('Query embedding returned an invalid vector.');
        }

        $validatedVector = [];

        foreach ($vector as $value) {
            if (!is_float($value)) {
                throw new UnexpectedValueException('Query embedding returned an invalid vector value.');
            }

            $validatedVector[] = $value;
        }

        return $validatedVector;
    }
}
