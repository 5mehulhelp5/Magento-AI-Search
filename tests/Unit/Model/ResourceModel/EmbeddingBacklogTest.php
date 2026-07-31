<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Model\ResourceModel;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog;
use InvalidArgumentException;
use LogicException;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EmbeddingBacklogTest extends TestCase
{
    public function testQueuesAnUpsertByChunkId(): void
    {
        $this->assertQueuesOperation(Operation::Upsert);
    }

    public function testQueuesADeletionByChunkId(): void
    {
        $this->assertQueuesOperation(Operation::Deletion);
    }

    /**
     * @return iterable<string, array{int, ?string, ?int, string}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'non-positive limit' => [
            0,
            null,
            null,
            'The embedding backlog batch limit must be positive.',
        ];
        yield 'cursor timestamp only' => [
            100,
            '2026-07-31 10:00:00',
            null,
            'Both embedding backlog cursor values must be provided together.',
        ];
        yield 'cursor ID only' => [
            100,
            null,
            10,
            'Both embedding backlog cursor values must be provided together.',
        ];
        yield 'negative cursor ID' => [
            100,
            '2026-07-31 10:00:00',
            -1,
            'The embedding backlog cursor ID must be non-negative.',
        ];
    }

    #[DataProvider('invalidQueries')]
    public function testRejectsInvalidPendingQueryArguments(
        int $limit,
        ?string $cursorUpdatedAt,
        ?int $cursorBacklogId,
        string $exceptionMessage
    ): void {
        $resource = $this->getMockBuilder(EmbeddingBacklog::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection'])
            ->getMock();
        $resource->expects(self::never())
            ->method('getConnection');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $resource->getPendingUpsertsForEmbedding(
            $limit,
            $cursorUpdatedAt,
            $cursorBacklogId
        );
    }

    public function testLoadsPendingUpsertsInStableOrder(): void
    {
        $selectCalls = [];
        $select = $this->createEmbeddingSelect(2, $selectCalls);
        $rows = [
            [
                EmbeddingBacklogInterface::BACKLOG_ID => '10',
                EmbeddingBacklogInterface::UPDATED_AT => '2026-07-31 10:00:00',
            ],
        ];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())
            ->method('select')
            ->willReturn($select);
        $connection->expects(self::once())
            ->method('fetchAll')
            ->with($select)
            ->willReturn($rows);
        $connection->expects(self::never())
            ->method('quoteInto');
        $resource = $this->createRetrievalResource($connection);

        self::assertSame(
            $rows,
            $resource->getPendingUpsertsForEmbedding(25)
        );
        $this->assertBaseSelectCalls($selectCalls, 25);
    }

    public function testLoadsPendingUpsertsAfterACompositeCursor(): void
    {
        $selectCalls = [];
        $select = $this->createEmbeddingSelect(3, $selectCalls);
        $quoteCalls = [];
        $connection = $this->createCursorConnection($select, $quoteCalls);
        $resource = $this->createRetrievalResource($connection);

        self::assertSame(
            [],
            $resource->getPendingUpsertsForEmbedding(
                10,
                '2026-07-31 10:00:00',
                42
            )
        );
        self::assertSame(
            [
                ['backlog.updated_at > ?', '2026-07-31 10:00:00'],
                ['backlog.updated_at = ?', '2026-07-31 10:00:00'],
                ['backlog.backlog_id > ?', 42],
            ],
            $quoteCalls
        );
        self::assertSame(
            ['(after_timestamp OR (at_timestamp AND after_id))'],
            $selectCalls['where'][2]
        );
    }

    public function testMarksUpsertsAsEmbedded(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())
            ->method('update')
            ->with(
                'embedding_backlog',
                [
                    EmbeddingBacklogInterface::STATUS => Status::Embedded->value,
                    EmbeddingBacklogInterface::LAST_ERROR_CATEGORY => null,
                ],
                self::updateConditions([10, 20])
            );
        $resource = $this->createUpdateResource($connection);

        $resource->markEmbeddedByIds([10, 20]);
    }

    public function testMarksUpsertsAsFailedAndIncrementsAttempts(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())
            ->method('update')
            ->with(
                'embedding_backlog',
                self::callback(
                    static function (array $values): bool {
                        self::assertSame(
                            Status::Failed->value,
                            $values[EmbeddingBacklogInterface::STATUS]
                        );
                        self::assertSame(
                            'embedder',
                            $values[EmbeddingBacklogInterface::LAST_ERROR_CATEGORY]
                        );
                        $attemptCount = $values[EmbeddingBacklogInterface::ATTEMPT_COUNT];
                        self::assertInstanceOf(Expression::class, $attemptCount);
                        self::assertSame('attempt_count + 1', (string) $attemptCount);

                        return true;
                    }
                ),
                self::updateConditions([30])
            );
        $resource = $this->createUpdateResource($connection);

        $resource->markFailedByIds([30], 'embedder');
    }

    public function testDoesNotUpdateAnEmptyIdSet(): void
    {
        $resource = $this->getMockBuilder(EmbeddingBacklog::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection'])
            ->getMock();
        $resource->expects(self::never())
            ->method('getConnection');

        $resource->markEmbeddedByIds([]);
        $resource->markFailedByIds([], 'embedder');
    }

    private function assertQueuesOperation(Operation $operation): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())
            ->method('insertOnDuplicate')
            ->with(
                'embedding_backlog',
                [
                    EmbeddingBacklogInterface::CHUNK_ID => 42,
                    EmbeddingBacklogInterface::OPERATION => $operation->value,
                    EmbeddingBacklogInterface::STATUS => Status::Pending->value,
                    EmbeddingBacklogInterface::ATTEMPT_COUNT => 0,
                    EmbeddingBacklogInterface::LAST_ERROR_CATEGORY => null,
                ],
                [
                    EmbeddingBacklogInterface::OPERATION,
                    EmbeddingBacklogInterface::STATUS,
                    EmbeddingBacklogInterface::ATTEMPT_COUNT,
                    EmbeddingBacklogInterface::LAST_ERROR_CATEGORY,
                ]
            );
        $resource = $this->createUpdateResource($connection);

        if ($operation === Operation::Upsert) {
            $resource->saveByChunkId(42);

            return;
        }

        $resource->deleteByChunkId(42);
    }

    /**
     * @param array<string, list<list<mixed>>> $calls
     */
    private function assertBaseSelectCalls(array $calls, int $limit): void
    {
        $this->assertSourceSelectCalls($calls);
        $this->assertJoinSelectCalls($calls);
        self::assertSame(
            [
                ['backlog.operation = ?', Operation::Upsert->value],
                ['backlog.status = ?', Status::Pending->value],
            ],
            $calls['where']
        );
        self::assertSame(
            [
                [
                    [
                        'backlog.updated_at ASC',
                        'backlog.backlog_id ASC',
                    ],
                ],
            ],
            $calls['order']
        );
        self::assertSame([[$limit]], $calls['limit']);
    }

    /**
     * @param array<string, list<list<mixed>>> $calls
     */
    private function assertSourceSelectCalls(array $calls): void
    {
        self::assertSame(
            [
                [
                    ['backlog' => 'embedding_backlog'],
                    [
                        EmbeddingBacklogInterface::BACKLOG_ID,
                        EmbeddingBacklogInterface::CHUNK_ID,
                        EmbeddingBacklogInterface::UPDATED_AT,
                    ],
                ],
            ],
            $calls['from']
        );
    }

    /**
     * @param array<string, list<list<mixed>>> $calls
     */
    private function assertJoinSelectCalls(array $calls): void
    {
        self::assertSame(
            [
                [
                    ['chunk' => 'resolved_davidbel_ai_search_chunk'],
                    'chunk.chunk_id = backlog.chunk_id',
                    [
                        ChunkInterface::CHUNK_INDEX,
                        ChunkInterface::CONTENT,
                        ChunkInterface::CONTENT_HASH,
                    ],
                ],
                [
                    ['document' => 'resolved_davidbel_ai_search_document'],
                    'document.document_id = chunk.document_id',
                    [
                        DocumentInterface::SOURCE_ENTITY_TYPE,
                        DocumentInterface::SOURCE_ENTITY_ID,
                        DocumentInterface::STORE_ID,
                        DocumentInterface::SOURCE_CODE,
                    ],
                ],
            ],
            $calls['join']
        );
    }

    /**
     * @param array<string, list<list<mixed>>> $calls
     */
    private function createEmbeddingSelect(
        int $whereCount,
        array &$calls
    ): Select&MockObject {
        $select = $this->createMock(Select::class);
        $calls = [
            'from' => [],
            'join' => [],
            'where' => [],
            'order' => [],
            'limit' => [],
        ];
        $this->captureSelectCalls($select, 'from', 1, $calls);
        $this->captureSelectCalls($select, 'join', 2, $calls);
        $this->captureSelectCalls($select, 'where', $whereCount, $calls);
        $this->captureSelectCalls($select, 'order', 1, $calls);
        $this->captureSelectCalls($select, 'limit', 1, $calls);

        return $select;
    }

    /**
     * @param non-empty-string $method
     * @param array<string, list<list<mixed>>> $calls
     */
    private function captureSelectCalls(
        Select&MockObject $select,
        string $method,
        int $count,
        array &$calls
    ): void {
        $select->expects(self::exactly($count))
            ->method($method)
            ->willReturnCallback(
                static function (mixed ...$arguments) use (
                    &$calls,
                    $method,
                    $select
                ): Select {
                    while ($arguments !== [] && end($arguments) === null) {
                        array_pop($arguments);
                    }

                    $calls[$method][] = $arguments;

                    return $select;
                }
            );
    }

    /**
     * @param list<array{string, mixed}> $quoteCalls
     */
    private function createCursorConnection(
        Select $select,
        array &$quoteCalls
    ): AdapterInterface&MockObject {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())
            ->method('select')
            ->willReturn($select);
        $connection->expects(self::once())
            ->method('fetchAll')
            ->with($select)
            ->willReturn([]);
        $connection->expects(self::exactly(3))
            ->method('quoteInto')
            ->willReturnCallback(
                static function (
                    string $condition,
                    mixed $value
                ) use (&$quoteCalls): string {
                    $quoteCalls[] = [$condition, $value];

                    return match ($condition) {
                        'backlog.updated_at > ?' => 'after_timestamp',
                        'backlog.updated_at = ?' => 'at_timestamp',
                        'backlog.backlog_id > ?' => 'after_id',
                        default => throw new LogicException(
                            'Unexpected cursor condition.'
                        ),
                    };
                }
            );

        return $connection;
    }

    private function createRetrievalResource(
        AdapterInterface $connection
    ): EmbeddingBacklog&MockObject {
        $resource = $this->getMockBuilder(EmbeddingBacklog::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable', 'getTable'])
            ->getMock();
        $resource->expects(self::once())
            ->method('getConnection')
            ->willReturn($connection);
        $resource->expects(self::once())
            ->method('getMainTable')
            ->willReturn('embedding_backlog');
        $resource->expects(self::exactly(2))
            ->method('getTable')
            ->willReturnCallback(
                static fn (string $table): string => 'resolved_' . $table
            );

        return $resource;
    }

    private function createUpdateResource(
        AdapterInterface $connection
    ): EmbeddingBacklog&MockObject {
        $resource = $this->getMockBuilder(EmbeddingBacklog::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $resource->expects(self::once())
            ->method('getConnection')
            ->willReturn($connection);
        $resource->expects(self::once())
            ->method('getMainTable')
            ->willReturn('embedding_backlog');

        return $resource;
    }

    /**
     * @param list<int> $backlogIds
     * @return array<string, mixed>
     */
    private static function updateConditions(array $backlogIds): array
    {
        return [
            EmbeddingBacklogInterface::BACKLOG_ID . ' IN (?)' => $backlogIds,
            EmbeddingBacklogInterface::OPERATION . ' = ?' => Operation::Upsert->value,
            EmbeddingBacklogInterface::STATUS . ' IN (?)' => [
                Status::Pending->value,
                Status::Failed->value,
            ],
        ];
    }
}
