<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Plugin\Adminhtml\Search\TestSemanticSearch;

use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Config\SearchResultConfig;
use DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch\ResultProvider\SearchScores;
use Magento\Store\Model\StoreManagerInterface;

class ChunkScoreCapture
{
    public function __construct(
        private readonly SearchScores $searchScores,
        private readonly SearchResultConfig $searchResultConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param array{
     *     hits?: array{
     *         hits?: list<array{_id?: int|string, _score?: int|float}>
     *     }
     * } $response
     * @return array<array-key, mixed>
     */
    public function afterSearch(OpenSearch $subject, array $response): array
    {
        $storeId = (int) (string) $this->storeManager->getStore()->getId();
        $minimumScore = $this->searchResultConfig->getMinimumScore($storeId);
        $this->searchScores->scoresByChunkId = $this->getScoresByChunkId(
            $response,
            $minimumScore
        );

        return $response;
    }

    /**
     * @param array{
     *     hits?: array{
     *         hits?: list<array{_id?: int|string, _score?: int|float}>
     *     }
     * } $response
     * @return array<int, float>
     */
    private function getScoresByChunkId(array $response, float $minimumScore): array
    {
        $hits = $response['hits']['hits'] ?? [];
        $scoresByChunkId = [];

        foreach ($hits as $hit) {
            $chunkId = (int) ($hit['_id'] ?? 0);
            $score = (float) ($hit['_score'] ?? 0.0);

            if ($chunkId < 1 || $score < $minimumScore) {
                continue;
            }

            $scoresByChunkId[$chunkId] = $score;
        }

        return $scoresByChunkId;
    }
}
