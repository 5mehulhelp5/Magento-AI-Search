<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use UnexpectedValueException;

class ProcessingItemMapper
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<ProcessingItem>
     */
    public function mapRows(array $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $items[] = new ProcessingItem(
                $this->toInteger($row[EmbeddingBacklogInterface::BACKLOG_ID] ?? null, 'backlog_id'),
                $this->toPositiveInteger($row[EmbeddingBacklogInterface::VERSION] ?? null, 'version'),
                $this->toString($row[EmbeddingBacklogInterface::UPDATED_AT] ?? null, 'updated_at'),
                $this->toInteger($row[EmbeddingBacklogInterface::CHUNK_ID] ?? null, 'chunk_id'),
                $this->toString($row[DocumentInterface::SOURCE_ENTITY_TYPE] ?? null, 'source_entity_type'),
                $this->toInteger($row[DocumentInterface::SOURCE_ENTITY_ID] ?? null, 'source_entity_id'),
                $this->toInteger($row[DocumentInterface::STORE_ID] ?? null, 'store_id'),
                $this->toString($row[DocumentInterface::SOURCE_CODE] ?? null, 'source_code'),
                $this->toInteger($row[ChunkInterface::CHUNK_INDEX] ?? null, 'chunk_index'),
                $this->toString($row[ChunkInterface::CONTENT] ?? null, 'content'),
                $this->toString($row[ChunkInterface::CONTENT_HASH] ?? null, 'content_hash')
            );
        }

        return $items;
    }

    private function toInteger(mixed $value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false || $integer < 0) {
            throw new UnexpectedValueException(sprintf('%s must be a non-negative integer.', $field));
        }

        return $integer;
    }

    private function toPositiveInteger(mixed $value, string $field): int
    {
        $integer = $this->toInteger($value, $field);

        if ($integer === 0) {
            throw new UnexpectedValueException(sprintf('%s must be a positive integer.', $field));
        }

        return $integer;
    }

    private function toString(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new UnexpectedValueException(sprintf('%s must be a string.', $field));
        }

        return $value;
    }
}
