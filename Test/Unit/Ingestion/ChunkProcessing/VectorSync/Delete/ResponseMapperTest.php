<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Ingestion\ChunkProcessing\VectorSync\Delete;

use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete\ResponseMapper;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\FailedItem;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Item;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\OpenSearchErrorMapper;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Result;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\ResultFactory;
use DavidBel\AiSearch\Model\EmbeddingBacklog\ErrorDetails;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class ResponseMapperTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(ResultFactory::class);
    }

    public function testMapsSuccessfulMissingAndFailedDeletions(): void
    {
        $created = $this->createItem(10, 42);
        $alreadyMissing = $this->createItem(20, 43);
        $failed = $this->createItem(30, 44);
        $result = self::createStub(Result::class);
        $factory = $this->createMock(ResultFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->with([
                'successfulItems' => [$created, $alreadyMissing],
                'failedItems' => [
                    new FailedItem(
                        $failed,
                        new ErrorDetails(
                            '500',
                            'OpenSearch bulk item failed with HTTP status 500.'
                        )
                    ),
                ],
            ])
            ->willReturn($result);

        self::assertSame(
            $result,
            (new ResponseMapper(new OpenSearchErrorMapper(), $factory))->map(
                [
                    'errors' => true,
                    'items' => [
                        ['delete' => ['_id' => '42', 'status' => 200]],
                        ['delete' => ['_id' => '43', 'status' => 404]],
                        ['delete' => ['_id' => '44', 'status' => 500]],
                    ],
                ],
                [$created, $alreadyMissing, $failed]
            )
        );
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidResponses(): iterable
    {
        yield 'missing errors flag' => [
            ['items' => []],
            'OpenSearch returned an invalid bulk delete response.',
        ];
        yield 'non-list items' => [
            ['errors' => false, 'items' => ['item' => []]],
            'OpenSearch returned an invalid bulk delete response.',
        ];
        yield 'unexpected item count' => [
            ['errors' => false, 'items' => []],
            'OpenSearch returned an unexpected bulk delete item count.',
        ];
        yield 'mismatched document ID' => [
            ['errors' => false, 'items' => [['delete' => ['_id' => '999', 'status' => 200]]]],
            'OpenSearch returned an invalid bulk delete item.',
        ];
    }

    /**
     * @param array<array-key, mixed> $response
     */
    #[DataProvider('invalidResponses')]
    public function testRejectsInvalidResponses(array $response, string $message): void
    {
        $factory = $this->createMock(ResultFactory::class);
        $factory->expects(self::never())->method('create');
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        (new ResponseMapper(new OpenSearchErrorMapper(), $factory))->map(
            $response,
            [$this->createItem(10, 42)]
        );
    }

    private function createItem(int $backlogId, int $chunkId): Item
    {
        return new Item(
            $backlogId,
            2,
            '2026-08-04 10:00:00',
            $chunkId,
            'product',
            99
        );
    }
}
