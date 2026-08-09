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
    private const bool USE_PREVIOUS_SEMANTIC_INDEX_DURING_REBUILD = true;

    public function usePreviousSemanticIndexDuringRebuild(): bool
    {
        return self::USE_PREVIOUS_SEMANTIC_INDEX_DURING_REBUILD;
    }
}
