<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync;

readonly class Item
{
    public function __construct(
        public int $backlogId,
        public int $backlogVersion,
        public string $backlogUpdatedAt,
        public int $chunkId,
        public string $sourceEntityType,
        public int $sourceEntityId
    ) {
    }
}
