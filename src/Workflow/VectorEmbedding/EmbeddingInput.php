<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\VectorEmbedding;

readonly class EmbeddingInput
{
    public function __construct(
        public int $backlogId,
        public string $backlogUpdatedAt,
        public int $chunkId,
        public string $sourceEntityType,
        public int $sourceEntityId,
        public int $storeId,
        public string $sourceCode,
        public int $chunkIndex,
        public string $content,
        public string $contentHash
    ) {
    }
}
