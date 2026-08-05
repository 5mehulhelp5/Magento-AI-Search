<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Item;
use UnexpectedValueException;

class ItemMapper
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<Item>
     */
    public function mapRows(array $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $items[] = new Item(
                $this->toInteger($row[EmbeddingBacklogInterface::BACKLOG_ID] ?? null, 'backlog_id'),
                $this->toPositiveInteger($row[EmbeddingBacklogInterface::VERSION] ?? null, 'version'),
                $this->toString($row[EmbeddingBacklogInterface::UPDATED_AT] ?? null, 'updated_at'),
                $this->toInteger($row[EmbeddingBacklogInterface::CHUNK_ID] ?? null, 'chunk_id'),
                $this->toString(
                    $row[EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE] ?? null,
                    'source_entity_type'
                ),
                $this->toInteger(
                    $row[EmbeddingBacklogInterface::SOURCE_ENTITY_ID] ?? null,
                    'source_entity_id'
                )
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
        if (!is_string($value) || $value === '') {
            throw new UnexpectedValueException(sprintf('%s must be a non-empty string.', $field));
        }

        return $value;
    }
}
