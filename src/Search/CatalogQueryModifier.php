<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Search;

use UnexpectedValueException;

class CatalogQueryModifier
{
    private const string SCORE_SCRIPT = "params.scores[doc['_id'].value]";

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function execute(array $query, Candidates $candidates): array
    {
        $body = $query['body'] ?? null;

        if (!is_array($body)) {
            throw new UnexpectedValueException('Magento returned an unexpected catalog search query.');
        }

        $searchQuery = $body['query'] ?? null;

        if (!is_array($searchQuery)) {
            throw new UnexpectedValueException('Magento returned an unexpected catalog search query.');
        }

        $boolQuery = $searchQuery['bool'] ?? null;

        if (!is_array($boolQuery)) {
            throw new UnexpectedValueException('Magento returned an unexpected catalog search query.');
        }

        unset($boolQuery['should'], $boolQuery['minimum_should_match']);

        $mustQueries = $boolQuery['must'] ?? [];

        if (!is_array($mustQueries) || !array_is_list($mustQueries)) {
            throw new UnexpectedValueException('Magento returned invalid catalog search conditions.');
        }

        $mustQueries[] = $this->createCandidateQuery($candidates);
        $boolQuery['must'] = $mustQueries;
        $searchQuery['bool'] = $boolQuery;
        $body['query'] = $searchQuery;
        $query['body'] = $body;

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function createCandidateQuery(Candidates $candidates): array
    {
        $scoresByProductId = $candidates->scoresByProductId;

        if ($scoresByProductId === []) {
            return ['match_none' => new \stdClass()];
        }

        return [
            'script_score' => [
                'query' => [
                    'ids' => [
                        'values' => array_map('strval', array_keys($scoresByProductId)),
                    ],
                ],
                'script' => [
                    'lang' => 'painless',
                    'source' => self::SCORE_SCRIPT,
                    'params' => [
                        'scores' => $scoresByProductId,
                    ],
                ],
            ],
        ];
    }
}
