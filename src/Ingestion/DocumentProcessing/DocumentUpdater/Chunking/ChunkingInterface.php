<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\Chunking;

interface ChunkingInterface
{
    /**
     * @return list<string>
     */
    public function chunk(
        string $text,
        int $maxTokens,
        int $overlapTokens,
        int $estimatedCharactersPerToken
    ): array;
}
