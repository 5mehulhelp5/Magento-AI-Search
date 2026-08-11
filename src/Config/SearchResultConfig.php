<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use UnexpectedValueException;

class SearchResultConfig
{
    private const string XML_PATH_ENABLED =
        'davidbel_ai_search_search_result/general/enabled';
    private const string XML_PATH_REQUEST_TIMEOUT_SECONDS =
        'davidbel_ai_search_search_result/general/request_timeout_seconds';
    private const string XML_PATH_USE_PREVIOUS_SEMANTIC_INDEX_DURING_REBUILD =
        'davidbel_ai_search_search_result/general/use_previous_semantic_index_during_rebuild';
    private const string XML_PATH_COLLAPSE_RESULTS_BY_PRODUCT =
        'davidbel_ai_search_search_result/search_relevance/collapse_results_by_product';
    private const string XML_PATH_PRODUCT_RESULT_LIMIT =
        'davidbel_ai_search_search_result/search_relevance/product_result_limit';
    private const string XML_PATH_CHUNK_CANDIDATE_LIMIT =
        'davidbel_ai_search_search_result/search_relevance/chunk_candidate_limit';
    private const string XML_PATH_MINIMUM_SCORE =
        'davidbel_ai_search_search_result/search_relevance/minimum_score';
    private const string XML_PATH_EMBEDDER_QUERY_TEMPLATE =
        'davidbel_ai_search_search_result/embedding/embedder_query_template';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getRequestTimeoutSeconds(int $storeId): int
    {
        return $this->getPositiveInteger(
            self::XML_PATH_REQUEST_TIMEOUT_SECONDS,
            $storeId
        );
    }

    public function usePreviousSemanticIndexDuringRebuild(int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_USE_PREVIOUS_SEMANTIC_INDEX_DURING_REBUILD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function shouldCollapseResultsByProduct(int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_COLLAPSE_RESULTS_BY_PRODUCT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getProductResultLimit(int $storeId): int
    {
        return $this->getPositiveInteger(
            self::XML_PATH_PRODUCT_RESULT_LIMIT,
            $storeId
        );
    }

    public function getChunkCandidateLimit(int $storeId): int
    {
        return $this->getPositiveInteger(
            self::XML_PATH_CHUNK_CANDIDATE_LIMIT,
            $storeId
        );
    }

    public function getMinimumScore(int $storeId): float
    {
        $value = filter_var(
            $this->scopeConfig->getValue(
                self::XML_PATH_MINIMUM_SCORE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            ),
            FILTER_VALIDATE_FLOAT
        );

        if (!is_float($value) || $value < 0) {
            throw new UnexpectedValueException(
                sprintf(
                    'Configuration path "%s" must contain a non-negative number.',
                    self::XML_PATH_MINIMUM_SCORE
                )
            );
        }

        return $value;
    }

    public function getEmbedderQueryTemplate(?int $storeId = null): string
    {
        if ($storeId === null) {
            return $this->getStringValue(self::XML_PATH_EMBEDDER_QUERY_TEMPLATE);
        }

        return $this->getStringValue(self::XML_PATH_EMBEDDER_QUERY_TEMPLATE, $storeId);
    }

    private function getPositiveInteger(string $path, int $storeId): int
    {
        $value = filter_var(
            $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId),
            FILTER_VALIDATE_INT
        );

        if (!is_int($value) || $value < 1) {
            throw new UnexpectedValueException(
                sprintf('Configuration path "%s" must contain a positive integer.', $path)
            );
        }

        return $value;
    }

    private function getStringValue(string $path, ?int $storeId = null): string
    {
        $value = $storeId === null
            ? $this->scopeConfig->getValue($path)
            : $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);

        if (!is_string($value)) {
            throw new UnexpectedValueException(
                sprintf('Configuration path "%s" must contain a string.', $path)
            );
        }

        return $value;
    }
}
