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

    private readonly int $maximumChunkTokens;

    private readonly int $chunkOverlapTokens;

    private readonly int $estimatedCharactersPerToken;

    /**
     * @param array<string, Chunking\ChunkingInterface> $chunkingStrategies
     */
    public function __construct(
        EmbedderConfig $embedderConfig,
        array $chunkingStrategies
    ) {
        if ($chunkingStrategies === []) {
            throw new InvalidArgumentException('At least one chunking strategy is required.');
        }

        $this->chunkingStrategies = array_values($chunkingStrategies);
        $this->maximumChunkTokens = $embedderConfig->getMaximumChunkTokens();
        $this->chunkOverlapTokens = $embedderConfig->getChunkOverlapTokens();
        $this->estimatedCharactersPerToken = $embedderConfig
            ->getEstimatedCharactersPerToken();
    }

    /**
     * @return list<string>
     */
    public function chunk(string $text): array
    {
        return $this->chunkingStrategies[0]->chunk(
            $text,
            $this->maximumChunkTokens,
            $this->chunkOverlapTokens,
            $this->estimatedCharactersPerToken
        );
    }
}
