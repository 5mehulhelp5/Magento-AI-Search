<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product;

readonly class ProductSource
{
    /**
     * @param list<ScopedSource> $scopedSources
     */
    public function __construct(
        public string $sourceCode,
        public array $scopedSources
    ) {
    }
}
