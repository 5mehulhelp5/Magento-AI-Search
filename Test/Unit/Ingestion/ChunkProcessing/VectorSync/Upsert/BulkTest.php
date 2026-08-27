<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Ingestion\ChunkProcessing\VectorSync\Upsert;

use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\FailedItem;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Item;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\OpenSearchErrorMapper;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Result;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\ResultFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Upsert\Bulk;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Upsert\Document;
use DavidBel\AiSearch\Model\EmbeddingBacklog\ErrorDetails;
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
        $openSearch = $this->createMock(OpenSearch::class);
        $openSearch->expects(self::once())
            ->method('bulkQuery')
            ->with(
                1,
                [
                    ['index' => ['_id' => '42']],
                    $this->documentBody(),
                    ['index' => ['_id' => '43']],
                    $this->documentBody(),
                ]
            )
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
                'failedItems' => [$this->createFailedItem($second)],
            ])
            ->willReturn($result);

        self::assertSame(
            $result,
            (new Bulk(
                $openSearch,
                new OpenSearchErrorMapper(),
                $factory
            ))->execute([$first, $second])
        );
    }

    public function testReturnsEmptyResultWithoutCallingOpenSearch(): void
    {
        $openSearch = $this->createMock(OpenSearch::class);
        $openSearch->expects(self::never())->method('bulkQuery');
        $result = self::createStub(Result::class);
        $factory = self::createStub(ResultFactory::class);
        $factory->method('create')->willReturn($result);

        self::assertSame(
            $result,
            (new Bulk($openSearch, new OpenSearchErrorMapper(), $factory))->execute([])
        );
    }

    public function testRejectsDocumentsFromDifferentIndexVersions(): void
    {
        $openSearch = $this->createMock(OpenSearch::class);
        $openSearch->expects(self::never())->method('bulkQuery');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must contain one index version');

        (new Bulk(
            $openSearch,
            new OpenSearchErrorMapper(),
            self::createStub(ResultFactory::class)
        ))->execute([$this->createDocument(10, 42), $this->createDocument(20, 43, 2)]);
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
        $openSearch = self::createStub(OpenSearch::class);
        $openSearch->method('bulkQuery')->willReturn($response);
        $factory = $this->createMock(ResultFactory::class);
        $factory->expects(self::never())->method('create');
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        (new Bulk(
            $openSearch,
            new OpenSearchErrorMapper(),
            $factory
        ))->execute([$this->createDocument(10, 42)]);
    }

    private function createDocument(int $backlogId, int $chunkId, int $indexVersion = 1): Document
    {
        return new Document(
            new Item(
                $backlogId,
                2,
                '2026-08-04 10:00:00',
                $chunkId,
                'product',
                99,
                $indexVersion
            ),
            1,
            'catalog_product_99',
            0,
            'text',
            'hash',
            [0.1, 0.2]
        );
    }

    private function createFailedItem(Document $document): FailedItem
    {
        return new FailedItem(
            $document->item,
            new ErrorDetails(
                '500',
                'OpenSearch bulk item failed with HTTP status 500.'
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function documentBody(): array
    {
        return [
            'source_entity_type' => 'product',
            'source_entity_id' => 99,
            'store_id' => 1,
            'source_code' => 'catalog_product_99',
            'vector' => [0.1, 0.2],
        ];
    }
}
