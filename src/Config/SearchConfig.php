<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Config;

class SearchConfig
{
    private const bool ENABLED = true;
    private const int REQUEST_TIMEOUT_SECONDS = 2;
    private const bool USE_PREVIOUS_SEMANTIC_INDEX_DURING_REBUILD = true;
    private const int CHUNK_RESULT_LIMIT = 1_000;
    private const float MINIMUM_SCORE = 0.46;

    public function isEnabled(): bool
    {
        return self::ENABLED;
    }

    public function getRequestTimeoutSeconds(): int
    {
        return self::REQUEST_TIMEOUT_SECONDS;
    }

    public function usePreviousSemanticIndexDuringRebuild(): bool
    {
        return self::USE_PREVIOUS_SEMANTIC_INDEX_DURING_REBUILD;
    }

    public function getChunkResultLimit(): int
    {
        return self::CHUNK_RESULT_LIMIT;
    }

    public function getMinimumScore(): float
    {
        return self::MINIMUM_SCORE;
    }
}
