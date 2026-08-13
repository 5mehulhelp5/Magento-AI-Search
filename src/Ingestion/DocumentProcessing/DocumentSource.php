<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing;

use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentSource\StoreScopedSource;

readonly class DocumentSource
{
    /**
     * @param list<StoreScopedSource> $storeScopedSources
     */
    public function __construct(
        public string $sourceCode,
        public string $parsingStrategy,
        public array $storeScopedSources
    ) {
    }
}
