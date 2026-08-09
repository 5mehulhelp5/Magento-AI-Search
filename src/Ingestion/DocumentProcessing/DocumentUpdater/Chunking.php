<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater;

use DavidBel\AiSearch\Config\EmbedderConfig;
use InvalidArgumentException;

class Chunking
{
    /**
     * @var list<Chunking\ChunkingInterface>
     */
    private readonly array $chunkingStrategies;

    /**
     * @param array<string, Chunking\ChunkingInterface> $chunkingStrategies
     */
    public function __construct(
        private readonly EmbedderConfig $embedderConfig,
        array $chunkingStrategies
    ) {
        if ($chunkingStrategies === []) {
            throw new InvalidArgumentException('At least one chunking strategy is required.');
        }

        $this->chunkingStrategies = array_values($chunkingStrategies);
    }

    /**
     * @return list<string>
     */
    public function chunk(string $text): array
    {
        //TODO map strategies and attributes via configuration
        return $this->chunkingStrategies[0]->chunk(
            $text,
            $this->embedderConfig->getMaximumChunkTokens(),
            $this->embedderConfig->getChunkOverlapTokens(),
            $this->embedderConfig->getEstimatedCharactersPerToken()
        );
    }
}
