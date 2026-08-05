<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Upsert;

use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Item;

readonly class Document
{
    /**
     * @param list<float> $vector
     */
    public function __construct(
        public Item $item,
        public int $storeId,
        public string $sourceCode,
        public int $chunkIndex,
        public string $content,
        public string $contentHash,
        public array $vector
    ) {
    }
}
