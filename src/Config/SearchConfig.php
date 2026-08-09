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
}
