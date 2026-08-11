<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Plugin\Config\ProcessorFacade;

use DavidBel\AiSearch\Indexer\Versioning;
use Magento\Config\Console\Command\ConfigSet\ProcessorFacade;

class InvalidateProductIndexer
{
    private const string SEARCH_SOURCE_PATH_PREFIX =
        'davidbel_ai_search_search_source/';

    public function __construct(
        private readonly Versioning $versioning
    ) {
    }

    public function afterProcessWithLockTarget(
        ProcessorFacade $subject,
        string $result,
        string $path
    ): string {
        if (!str_starts_with($path, self::SEARCH_SOURCE_PATH_PREFIX)) {
            return $result;
        }

        $this->versioning->invalidateProductIndexerWhenNeeded();

        return $result;
    }
}
