<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\ChunkProcessing;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingItemMapper;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class ProcessingItemMapperTest extends TestCase
{
    public function testMapsPersistedRowsToProcessingItems(): void
    {
        $items = (new ProcessingItemMapper())->mapRows([$this->createRow()]);

        self::assertCount(1, $items);
        self::assertSame(10, $items[0]->backlogId);
        self::assertSame(2, $items[0]->backlogVersion);
        self::assertSame('2026-08-04 10:00:00', $items[0]->backlogUpdatedAt);
        self::assertSame(42, $items[0]->chunkId);
        self::assertSame('product', $items[0]->sourceEntityType);
        self::assertSame(99, $items[0]->sourceEntityId);
        self::assertSame(1, $items[0]->storeId);
        self::assertSame('catalog_product_99', $items[0]->sourceCode);
        self::assertSame(0, $items[0]->chunkIndex);
        self::assertSame('text', $items[0]->content);
        self::assertSame('hash', $items[0]->contentHash);
        self::assertSame('Title', $items[0]->title);
    }

    public function testRejectsANonPositiveBacklogVersion(): void
    {
        $row = $this->createRow();
        $row[EmbeddingBacklogInterface::BACKLOG_VERSION] = '0';

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('backlog_version must be a positive integer.');

        (new ProcessingItemMapper())->mapRows([$row]);
    }

    public function testRejectsANonStringContent(): void
    {
        $row = $this->createRow();
        $row[ChunkInterface::CONTENT] = null;

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('content must be a string.');

        (new ProcessingItemMapper())->mapRows([$row]);
    }

    public function testRejectsNegativeIdentifier(): void
    {
        $row = $this->createRow();
        $row[EmbeddingBacklogInterface::BACKLOG_ID] = '-1';

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('backlog_id must be a non-negative integer');

        (new ProcessingItemMapper())->mapRows([$row]);
    }

    /**
     * @return array<string, mixed>
     */
    private function createRow(): array
    {
        return [
            EmbeddingBacklogInterface::BACKLOG_ID => '10',
            EmbeddingBacklogInterface::BACKLOG_VERSION => '2',
            EmbeddingBacklogInterface::INDEX_VERSION => '7',
            EmbeddingBacklogInterface::UPDATED_AT => '2026-08-04 10:00:00',
            EmbeddingBacklogInterface::CHUNK_ID => '42',
            DocumentInterface::SOURCE_ENTITY_TYPE => 'product',
            DocumentInterface::SOURCE_ENTITY_ID => '99',
            DocumentInterface::STORE_ID => '1',
            DocumentInterface::SOURCE_CODE => 'catalog_product_99',
            ChunkInterface::CHUNK_INDEX => '0',
            ChunkInterface::CONTENT => 'text',
            ChunkInterface::CONTENT_HASH => 'hash',
            DocumentInterface::TITLE => 'Title',
        ];
    }
}
