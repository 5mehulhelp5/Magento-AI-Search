<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing;

use InvalidArgumentException;

class Chunking
{
    public const int MAX_TOKENS = 350;
    public const int OVERLAP_TOKENS = 50;
    public const int ESTIMATED_CHARACTERS_PER_TOKEN = 4;

    /**
     * @var list<Chunking\ChunkingInterface>
     */
    private readonly array $chunkingStrategies;

    /**
     * @param array<string, Chunking\ChunkingInterface> $chunkingStrategies
     */
    public function __construct(array $chunkingStrategies)
    {
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
            self::MAX_TOKENS,
            self::OVERLAP_TOKENS,
            self::ESTIMATED_CHARACTERS_PER_TOKEN
        );
    }
}
