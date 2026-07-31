<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Workflow\VectorEmbedding;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingInput;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingInputMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class EmbeddingInputMapperTest extends TestCase
{
    public function testMapsDatabaseRowsToTypedInputs(): void
    {
        $mapper = new EmbeddingInputMapper();

        self::assertEquals(
            [
                new EmbeddingInput(
                    10,
                    '2026-07-31 10:00:00',
                    20,
                    'product',
                    30,
                    2,
                    'description',
                    4,
                    'content',
                    'content-hash'
                ),
            ],
            $mapper->mapRows([self::validRow()])
        );
    }

    public function testMapsNoRowsToNoInputs(): void
    {
        self::assertSame([], (new EmbeddingInputMapper())->mapRows([]));
    }

    /**
     * @return iterable<string, array{string, mixed, string}>
     */
    public static function invalidFields(): iterable
    {
        yield 'missing integer' => [
            EmbeddingBacklogInterface::BACKLOG_ID,
            null,
            'backlog_id must be a non-negative integer.',
        ];
        yield 'non-numeric integer' => [
            EmbeddingBacklogInterface::CHUNK_ID,
            'invalid',
            'chunk_id must be a non-negative integer.',
        ];
        yield 'negative integer' => [
            DocumentInterface::STORE_ID,
            -1,
            'store_id must be a non-negative integer.',
        ];
        yield 'non-string value' => [
            ChunkInterface::CONTENT,
            123,
            'content must be a string.',
        ];
        yield 'missing string' => [
            EmbeddingBacklogInterface::UPDATED_AT,
            null,
            'updated_at must be a string.',
        ];
    }

    #[DataProvider('invalidFields')]
    public function testRejectsAnInvalidRowField(
        string $field,
        mixed $value,
        string $exceptionMessage
    ): void {
        $row = self::validRow();
        $row[$field] = $value;

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($exceptionMessage);

        (new EmbeddingInputMapper())->mapRows([$row]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function validRow(): array
    {
        return [
            EmbeddingBacklogInterface::BACKLOG_ID => '10',
            EmbeddingBacklogInterface::UPDATED_AT => '2026-07-31 10:00:00',
            EmbeddingBacklogInterface::CHUNK_ID => '20',
            DocumentInterface::SOURCE_ENTITY_TYPE => 'product',
            DocumentInterface::SOURCE_ENTITY_ID => '30',
            DocumentInterface::STORE_ID => '2',
            DocumentInterface::SOURCE_CODE => 'description',
            ChunkInterface::CHUNK_INDEX => '4',
            ChunkInterface::CONTENT => 'content',
            ChunkInterface::CONTENT_HASH => 'content-hash',
        ];
    }
}
