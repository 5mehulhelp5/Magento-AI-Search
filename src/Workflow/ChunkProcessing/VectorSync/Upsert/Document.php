<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Upsert;

use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Item;

readonly class Document extends Item
{
    /**
     * @param list<float> $vector
     */
    public function __construct(
        int $backlogId,
        int $chunkId,
        string $sourceEntityType,
        int $sourceEntityId,
        public int $storeId,
        public string $sourceCode,
        public int $chunkIndex,
        public string $content,
        public string $contentHash,
        public array $vector
    ) {
        parent::__construct(
            $backlogId,
            $chunkId,
            $sourceEntityType,
            $sourceEntityId
        );
    }
}
