<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\ChunkProcessing\VectorSync\Upsert;

use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Index;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Item;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Result;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\ResultFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Upsert\Bulk;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Upsert\Document;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class BulkTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(ResultFactory::class);
    }

    public function testBuildsBulkRequestAndMapsPartialFailure(): void
    {
        $first = $this->createDocument(10, 42);
        $second = $this->createDocument(20, 43);
        $index = $this->createMock(Index::class);
        $index->expects(self::exactly(2))->method('getName')->willReturn('ai-search');
        $index->expects(self::once())
            ->method('bulkQuery')
            ->with([
                ['index' => ['_index' => 'ai-search', '_id' => '42']],
                $this->documentBody(42),
                ['index' => ['_index' => 'ai-search', '_id' => '43']],
                $this->documentBody(43),
            ])
            ->willReturn([
                'errors' => true,
                'items' => [
                    ['index' => ['_id' => '42', 'status' => 201]],
                    ['index' => ['_id' => '43', 'status' => 500]],
                ],
            ]);
        $result = self::createStub(Result::class);
        $factory = $this->createMock(ResultFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->with([
                'successfulItems' => [$first->item],
                'failedItems' => [$second->item],
            ])
            ->willReturn($result);

        self::assertSame($result, (new Bulk($index, $factory))->execute([$first, $second]));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidResponses(): iterable
    {
        yield 'invalid shape' => [
            ['errors' => 'false', 'items' => []],
            'OpenSearch returned an invalid bulk response.',
        ];
        yield 'unexpected item count' => [
            ['errors' => false, 'items' => []],
            'OpenSearch returned an unexpected bulk item count.',
        ];
        yield 'inconsistent error flag' => [
            ['errors' => false, 'items' => [['index' => ['_id' => '42', 'status' => 500]]]],
            'OpenSearch returned inconsistent bulk error information.',
        ];
        yield 'mismatched document ID' => [
            ['errors' => false, 'items' => [['index' => ['_id' => '999', 'status' => 200]]]],
            'OpenSearch returned an invalid bulk item.',
        ];
    }

    /**
     * @param array<array-key, mixed> $response
     */
    #[DataProvider('invalidResponses')]
    public function testRejectsInvalidResponses(array $response, string $message): void
    {
        $index = self::createStub(Index::class);
        $index->method('getName')->willReturn('ai-search');
        $index->method('bulkQuery')->willReturn($response);
        $factory = $this->createMock(ResultFactory::class);
        $factory->expects(self::never())->method('create');
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        (new Bulk($index, $factory))->execute([$this->createDocument(10, 42)]);
    }

    private function createDocument(int $backlogId, int $chunkId): Document
    {
        return new Document(
            new Item(
                $backlogId,
                2,
                '2026-08-04 10:00:00',
                $chunkId,
                'product',
                99
            ),
            1,
            'catalog_product_99',
            0,
            'text',
            'hash',
            [0.1, 0.2]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function documentBody(int $chunkId): array
    {
        return [
            'chunk_id' => $chunkId,
            'source_entity_type' => 'product',
            'source_entity_id' => 99,
            'store_id' => 1,
            'source_code' => 'catalog_product_99',
            'chunk_index' => 0,
            'content' => 'text',
            'content_hash' => 'hash',
            'vector' => [0.1, 0.2],
        ];
    }
}
