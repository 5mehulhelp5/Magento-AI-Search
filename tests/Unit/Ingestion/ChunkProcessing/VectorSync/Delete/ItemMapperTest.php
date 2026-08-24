<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\ChunkProcessing\VectorSync\Delete;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete\ItemMapper;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class ItemMapperTest extends TestCase
{
    public function testMapsDeletionTombstones(): void
    {
        $items = (new ItemMapper())->mapRows([$this->createRow()]);

        self::assertCount(1, $items);
        self::assertSame(10, $items[0]->backlogId);
        self::assertSame(3, $items[0]->backlogVersion);
        self::assertSame(42, $items[0]->chunkId);
        self::assertSame('product', $items[0]->sourceEntityType);
        self::assertSame(99, $items[0]->sourceEntityId);
    }

    public function testRejectsAnEmptySourceEntityType(): void
    {
        $row = $this->createRow();
        $row[EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE] = '';

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('source_entity_type must be a non-empty string.');

        (new ItemMapper())->mapRows([$row]);
    }

    public function testRejectsANonPositiveBacklogVersion(): void
    {
        $row = $this->createRow();
        $row[EmbeddingBacklogInterface::BACKLOG_VERSION] = '0';

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('backlog_version must be a positive integer.');

        (new ItemMapper())->mapRows([$row]);
    }

    public function testRejectsNegativeIdentifier(): void
    {
        $row = $this->createRow();
        $row[EmbeddingBacklogInterface::BACKLOG_ID] = '-1';

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('backlog_id must be a non-negative integer');

        (new ItemMapper())->mapRows([$row]);
    }

    /**
     * @return array<string, mixed>
     */
    private function createRow(): array
    {
        return [
            EmbeddingBacklogInterface::BACKLOG_ID => '10',
            EmbeddingBacklogInterface::BACKLOG_VERSION => '3',
            EmbeddingBacklogInterface::INDEX_VERSION => '7',
            EmbeddingBacklogInterface::UPDATED_AT => '2026-08-04 10:00:00',
            EmbeddingBacklogInterface::CHUNK_ID => '42',
            EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE => 'product',
            EmbeddingBacklogInterface::SOURCE_ENTITY_ID => '99',
        ];
    }
}
