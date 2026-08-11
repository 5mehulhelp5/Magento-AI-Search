<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Search;

use DavidBel\AiSearch\Config\EmbeddedAttributesConfig;
use DavidBel\AiSearch\Config\SearchResultConfig;
use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex;
use UnexpectedValueException;

use function is_finite;

class VectorSearch
{
    public function __construct(
        private readonly OpenSearch $openSearch,
        private readonly EmbeddedAttributesConfig $embeddedAttributesConfig,
        private readonly SearchResultConfig $searchResultConfig
    ) {
    }

    /**
     * @param list<float> $vector
     */
    public function execute(array $vector, int $storeId, PhysicalIndex $physicalIndex): Candidates
    {
        $response = $this->openSearch->search(
            $physicalIndex,
            $this->createQuery($vector, $storeId)
        );

        return new Candidates(
            $this->getScoresByProductId(
                $response,
                $this->searchResultConfig->getMinimumScore($storeId)
            )
        );
    }

    /**
     * @param list<float> $vector
     * @return array<string, mixed>
     */
    private function createQuery(array $vector, int $storeId): array
    {
        $query = [
            'size' => $this->searchResultConfig->getProductResultLimit($storeId),
            '_source' => ['source_entity_id'],
            'query' => [
                'knn' => [
                    'vector' => [
                        'vector' => $vector,
                        'k' => $this->searchResultConfig->getChunkCandidateLimit($storeId),
                        'filter' => [
                            'bool' => [
                                'filter' => [
                                    ['term' => ['source_entity_type' => 'product']],
                                    ['term' => ['store_id' => $storeId]],
                                    ['terms' => ['source_code' => $this->getAttributeCodes()]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (!$this->searchResultConfig->shouldCollapseResultsByProduct($storeId)) {
            return $query;
        }

        $query['collapse'] = [
            'field' => 'source_entity_id',
        ];

        return $query;
    }

    /**
     * @return list<string>
     */
    private function getAttributeCodes(): array
    {
        $attributeCodes = [];

        foreach ($this->embeddedAttributesConfig->getAttributes() as $embeddedAttribute) {
            $attributeCodes[] = $embeddedAttribute->attributeCode;
        }

        return $attributeCodes;
    }

    /**
     * @param array<array-key, mixed> $response
     * @return array<int, float>
     */
    private function getScoresByProductId(array $response, float $minimumScore): array
    {
        $hitContainer = $response['hits'] ?? null;

        if (!is_array($hitContainer)) {
            throw new UnexpectedValueException('Semantic search returned an invalid hit list.');
        }

        $hits = $hitContainer['hits'] ?? null;

        if (!is_array($hits) || !array_is_list($hits)) {
            throw new UnexpectedValueException('Semantic search returned an invalid hit list.');
        }

        $scoresByProductId = [];

        foreach ($hits as $hit) {
            [$productId, $score] = $this->getProductScore($hit);
            $highestScore = $this->getHighestRelevantScore(
                $scoresByProductId[$productId] ?? null,
                $score,
                $minimumScore
            );

            if ($highestScore === null) {
                continue;
            }

            $scoresByProductId[$productId] = $highestScore;
        }

        return $scoresByProductId;
    }

    private function getHighestRelevantScore(
        ?float $currentScore,
        float $candidateScore,
        float $minimumScore
    ): ?float {
        if ($candidateScore < $minimumScore) {
            return $currentScore;
        }

        if ($currentScore !== null && $currentScore >= $candidateScore) {
            return $currentScore;
        }

        return $candidateScore;
    }

    /**
     * @return array{int, float}
     */
    private function getProductScore(mixed $hit): array
    {
        if (!is_array($hit)) {
            throw new UnexpectedValueException('Semantic search returned an invalid product hit.');
        }

        return [
            $this->getProductId($hit['_source'] ?? null),
            $this->getScore($hit['_score'] ?? null),
        ];
    }

    private function getProductId(mixed $source): int
    {
        $productId = is_array($source) ? ($source['source_entity_id'] ?? null) : null;

        if (!is_int($productId) || $productId < 1) {
            throw new UnexpectedValueException('Semantic search returned an invalid product hit.');
        }

        return $productId;
    }

    private function getScore(mixed $score): float
    {
        if (!is_int($score) && !is_float($score)) {
            throw new UnexpectedValueException('Semantic search returned an invalid product hit.');
        }

        $score = (float) $score;

        if (!is_finite($score) || $score < 0.0) {
            throw new UnexpectedValueException('Semantic search returned an invalid product score.');
        }

        return $score;
    }
}
