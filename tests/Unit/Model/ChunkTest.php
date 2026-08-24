<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Model;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Model\Chunk;
use DavidBel\AiSearch\Model\ResourceModel\Chunk as ChunkResource;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class ChunkTest extends TestCase
{
    private Chunk $chunk;

    protected function setUp(): void
    {
        $this->chunk = new Chunk(
            self::createStub(Context::class),
            self::createStub(Registry::class),
            self::createStub(ExtensionAttributesFactory::class),
            self::createStub(AttributeValueFactory::class),
            self::createStub(ChunkResource::class)
        );
    }

    public function testMapsPersistedDataToTheServiceContract(): void
    {
        $this->chunk->setData(ChunkInterface::CHUNK_ID, '15');
        $this->chunk->setChunkId(15);
        $this->chunk->setDocumentId(12);
        $this->chunk->setChunkIndex(2);
        $this->chunk->setContent('A normalized chunk.');
        $this->chunk->setContentHash(str_repeat('b', 64));
        $this->chunk->setCreatedAt('2026-07-28 10:00:00');
        $this->chunk->setUpdatedAt('2026-07-28 11:00:00');

        self::assertSame(15, $this->chunk->getChunkId());
        self::assertSame(12, $this->chunk->getDocumentId());
        self::assertSame(2, $this->chunk->getChunkIndex());
        self::assertSame('A normalized chunk.', $this->chunk->getContent());
        self::assertSame(str_repeat('b', 64), $this->chunk->getContentHash());
        self::assertSame('2026-07-28 10:00:00', $this->chunk->getCreatedAt());
        self::assertSame('2026-07-28 11:00:00', $this->chunk->getUpdatedAt());
    }

    public function testReturnsNullForUnsetOptionalValues(): void
    {
        self::assertNull($this->chunk->getChunkId());
        self::assertNull($this->chunk->getCreatedAt());
        self::assertNull($this->chunk->getUpdatedAt());
    }

    public function testRejectsInvalidOptionalString(): void
    {
        $this->chunk->setData(ChunkInterface::CREATED_AT, 10);

        $this->expectException(UnexpectedValueException::class);
        $this->chunk->getCreatedAt();
    }

    public function testRejectsInvalidRequiredString(): void
    {
        $this->chunk->setData(ChunkInterface::CONTENT, 10);

        $this->expectException(UnexpectedValueException::class);
        $this->chunk->getContent();
    }

    public function testRejectsInvalidPersistedData(): void
    {
        $this->chunk->setData(ChunkInterface::DOCUMENT_ID, 'not-an-integer');

        $this->expectException(UnexpectedValueException::class);

        $this->chunk->getDocumentId();
    }
}
