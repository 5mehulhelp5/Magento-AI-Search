<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider;

readonly class StoreScopedSource
{
    public function __construct(
        public int $storeId,
        public string $content,
        public ?string $title = null
    ) {
    }
}
