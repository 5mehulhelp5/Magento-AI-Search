<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Search;

use DavidBel\AiSearch\Config\EmbeddedAttributesConfig;
use Magento\Elasticsearch\SearchAdapter\ConnectionManager;
use Magento\OpenSearch\Model\SearchClient;
use RuntimeException;
use UnexpectedValueException;

use function is_finite;

class VectorSearch
{
    private const string INDEX_NAME = 'davidbel_ai_search_chunks';
    private const int CHUNK_RESULT_LIMIT = 1000;
    private const float MINIMUM_SCORE = 0.46;

    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly EmbeddedAttributesConfig $embeddedAttributesConfig
    ) {
    }

    /**
     * @param list<float> $vector
     */
    public function execute(array $vector, int $storeId): Candidates
    {
        $response = $this->getClient()->query([
            'index' => self::INDEX_NAME,
            'body' => $this->createQuery($vector, $storeId),
        ]);

        return new Candidates($this->getScoresByProductId($response));
    }

    /**
     * @param list<float> $vector
     * @return array<string, mixed>
     */
    private function createQuery(array $vector, int $storeId): array
    {
        return [
            'size' => self::CHUNK_RESULT_LIMIT,
            '_source' => ['source_entity_id'],
            'query' => [
                'knn' => [
                    'vector' => [
                        'vector' => $vector,
                        'k' => self::CHUNK_RESULT_LIMIT,
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
    private function getScoresByProductId(array $response): array
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
                $score
            );

            if ($highestScore === null) {
                continue;
            }

            $scoresByProductId[$productId] = $highestScore;
        }

        return $scoresByProductId;
    }

    private function getHighestRelevantScore(?float $currentScore, float $candidateScore): ?float
    {
        if ($candidateScore < self::MINIMUM_SCORE) {
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

    private function getClient(): SearchClient
    {
        $client = $this->connectionManager->getConnection();

        if (!$client instanceof SearchClient) {
            throw new RuntimeException('Magento is not configured to use OpenSearch.');
        }

        return $client;
    }
}
