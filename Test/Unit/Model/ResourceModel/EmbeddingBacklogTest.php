<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Model\ResourceModel;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklog\FullReindexStatus;
use DavidBel\AiSearch\Model\EmbeddingBacklog\ErrorDetails;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Maintenance;
use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
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
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    public function testQueuesAnUpsertByChunkId(): void
    {
        $this->assertQueuesOperation(Operation::Upsert);
    }

    public function testQueuesADeleteByChunkId(): void
    {
        $this->assertQueuesOperation(Operation::Delete);
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
            7,
            $limit,
            $cursorUpdatedAt,
            $cursorBacklogId
        );
    }

    public function testLoadsPendingUpsertsInStableOrder(): void
    {
        $selectCalls = [];
        $select = $this->createEmbeddingSelect(3, $selectCalls);
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
            $resource->getPendingUpsertsForEmbedding(7, 25)
        );
        $this->assertBaseSelectCalls($selectCalls, 25);
    }

    public function testLoadsPendingUpsertsAfterACompositeCursor(): void
    {
        $selectCalls = [];
        $select = $this->createEmbeddingSelect(4, $selectCalls);
        $quoteCalls = [];
        $connection = $this->createCursorConnection($select, $quoteCalls);
        $resource = $this->createRetrievalResource($connection);

        self::assertSame(
            [],
            $resource->getPendingUpsertsForEmbedding(
                7,
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
            $selectCalls['where'][3]
        );
    }

    public function testMarksUpsertsAsDone(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $this->configureQuotedIdentifiers($connection);
        $connection->expects(self::once())
            ->method('update')
            ->with(
                'embedding_backlog',
                [
                    EmbeddingBacklogInterface::STATUS => Status::Done->value,
                    EmbeddingBacklogInterface::ATTEMPT_COUNT => new Expression(
                        EmbeddingBacklogInterface::ATTEMPT_COUNT . ' + 1'
                    ),
                    EmbeddingBacklogInterface::LAST_ERROR_STAGE => null,
                    EmbeddingBacklogInterface::LAST_ERROR_CODE => null,
                    EmbeddingBacklogInterface::LAST_ERROR_MESSAGE => null,
                ],
                self::versionUpdateConditions([10 => 2, 20 => 3])
            );
        $resource = $this->createUpdateResource($connection);

        $resource->markDoneByVersions([10 => 2, 20 => 3]);
    }

    public function testMarksUpsertsAsFailedAndIncrementsAttempts(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $this->configureQuotedIdentifiers($connection);
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
                            $values[EmbeddingBacklogInterface::LAST_ERROR_STAGE]
                        );
                        self::assertSame(
                            'provider_unavailable',
                            $values[EmbeddingBacklogInterface::LAST_ERROR_CODE]
                        );
                        self::assertSame(
                            'Embedding provider unavailable.',
                            $values[EmbeddingBacklogInterface::LAST_ERROR_MESSAGE]
                        );
                        $attemptCount = $values[EmbeddingBacklogInterface::ATTEMPT_COUNT];
                        self::assertInstanceOf(Expression::class, $attemptCount);
                        self::assertSame('attempt_count + 1', (string) $attemptCount);

                        return true;
                    }
                ),
                self::versionUpdateConditions([30 => 4])
            );
        $resource = $this->createUpdateResource($connection);

        $resource->markFailedByVersions(
            [30 => 4],
            'embedder',
            new ErrorDetails(
                'provider_unavailable',
                'Embedding provider unavailable.'
            )
        );
    }

    public function testDoesNotUpdateAnEmptyIdSet(): void
    {
        $resource = $this->getMockBuilder(EmbeddingBacklog::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection'])
            ->getMock();
        $resource->expects(self::never())
            ->method('getConnection');

        $resource->markDoneByVersions([]);
        $resource->markFailedByVersions(
            [],
            'embedder',
            new ErrorDetails(null, 'Embedding failed.')
        );
    }

    public function testRetriesFailedRowsBelowTheAttemptThreshold(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())
            ->method('update')
            ->with(
                'embedding_backlog',
                [EmbeddingBacklogInterface::STATUS => Status::Pending->value],
                [
                    EmbeddingBacklogInterface::STATUS . ' = ?' => Status::Failed->value,
                    EmbeddingBacklogInterface::INDEX_VERSION . ' = ?' => 7,
                    EmbeddingBacklogInterface::ATTEMPT_COUNT . ' < ?' => 3,
                ]
            )
            ->willReturn(4);

        self::assertSame(
            4,
            $this->createMaintenance($connection)->markFailedAsPending(7, 3)
        );
    }

    public function testRejectsANonPositiveRetryThreshold(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The retry attempt threshold must be positive.');

        (new Maintenance(self::createStub(CollectionFactory::class)))
            ->markFailedAsPending(7, 0);
    }

    public function testDeletesExhaustedFailuresAndExpiredResults(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::exactly(4))
            ->method('quoteInto')
            ->willReturnOnConsecutiveCalls(
                'failed_status',
                'attempt_limit',
                'result_status',
                'expiration_cutoff'
            );
        $connection->expects(self::once())
            ->method('delete')
            ->with(
                'embedding_backlog',
                '((failed_status AND attempt_limit)'
                . ' OR (result_status AND expiration_cutoff))'
            )
            ->willReturn(6);

        self::assertSame(
            6,
            $this->createMaintenance($connection)
                ->deleteExhaustedUpsertsOrExpiredResults(
                    3,
                    '2026-08-03 10:00:00'
                )
        );
    }

    public function testRejectsAnEmptyCleanupCutoff(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The backlog expiration cutoff must not be empty.');

        (new Maintenance(self::createStub(CollectionFactory::class)))
            ->deleteExhaustedUpsertsOrExpiredResults(3, '');
    }

    private function assertQueuesOperation(Operation $operation): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $this->configureQuotedIdentifiers($connection);
        $connection->expects(self::once())
            ->method('insertOnDuplicate')
            ->with(
                'embedding_backlog',
                [
                    EmbeddingBacklogInterface::CHUNK_ID => 42,
                    EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE => 'product',
                    EmbeddingBacklogInterface::SOURCE_ENTITY_ID => 99,
                    EmbeddingBacklogInterface::OPERATION => $operation->value,
                    EmbeddingBacklogInterface::STATUS => Status::Pending->value,
                    EmbeddingBacklogInterface::INDEX_VERSION => 7,
                    EmbeddingBacklogInterface::FULL_REINDEX_STATUS => FullReindexStatus::Pending->value,
                    EmbeddingBacklogInterface::BACKLOG_VERSION => 1,
                    EmbeddingBacklogInterface::ATTEMPT_COUNT => 0,
                    EmbeddingBacklogInterface::LAST_ERROR_STAGE => null,
                    EmbeddingBacklogInterface::LAST_ERROR_CODE => null,
                    EmbeddingBacklogInterface::LAST_ERROR_MESSAGE => null,
                ],
                self::callback(self::duplicateFieldsAreVersioned(...))
            );
        $resource = $this->createUpdateResource($connection);

        if ($operation === Operation::Upsert) {
            $resource->saveByChunkId(42, 'product', 99, 7, FullReindexStatus::Pending);

            return;
        }

        $resource->deleteByChunkId(42, 'product', 99, 7, FullReindexStatus::Pending);
    }

    /**
     * @param array<int|string, mixed> $fields
     */
    private static function duplicateFieldsAreVersioned(array $fields): bool
    {
        self::assertSame(
            [
                EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE,
                EmbeddingBacklogInterface::SOURCE_ENTITY_ID,
                EmbeddingBacklogInterface::OPERATION,
                EmbeddingBacklogInterface::STATUS,
            ],
            array_slice($fields, 0, 4)
        );
        $fullReindexStatus = $fields[EmbeddingBacklogInterface::FULL_REINDEX_STATUS];
        self::assertInstanceOf(Expression::class, $fullReindexStatus);
        self::assertSame(
            'IF(`index_version` <> VALUES(`index_version`), VALUES(`full_reindex_status`), '
            . 'GREATEST(`full_reindex_status`, VALUES(`full_reindex_status`)))',
            (string) $fullReindexStatus
        );
        self::assertSame(EmbeddingBacklogInterface::INDEX_VERSION, $fields[4]);
        $backlogVersion = $fields[EmbeddingBacklogInterface::BACKLOG_VERSION];
        self::assertInstanceOf(Expression::class, $backlogVersion);
        self::assertSame('backlog_version + 1', (string) $backlogVersion);
        self::assertSame(
            [
                EmbeddingBacklogInterface::ATTEMPT_COUNT,
                EmbeddingBacklogInterface::LAST_ERROR_STAGE,
                EmbeddingBacklogInterface::LAST_ERROR_CODE,
                EmbeddingBacklogInterface::LAST_ERROR_MESSAGE,
            ],
            [$fields[5], $fields[6], $fields[7], $fields[8]]
        );

        return true;
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
                ['backlog.index_version = ?', 7],
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
                        EmbeddingBacklogInterface::BACKLOG_VERSION,
                        EmbeddingBacklogInterface::INDEX_VERSION,
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
                        DocumentInterface::TITLE,
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

    private function createMaintenance(AdapterInterface $connection): Maintenance
    {
        $resource = $this->createUpdateResource($connection);
        $collection = self::createStub(Collection::class);
        $collection->method('getResourceModel')->willReturn($resource);
        $collectionFactory = self::createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        return new Maintenance($collectionFactory);
    }

    /**
     * @param array<int, int> $backlogVersions
     * @return array<int|string, mixed>
     */
    private static function versionUpdateConditions(array $backlogVersions): array
    {
        $pairs = [];

        foreach ($backlogVersions as $backlogId => $version) {
            $pairs[] = sprintf('(%d, %d)', $backlogId, $version);
        }

        return [
            sprintf(
                '(`backlog_id`, `backlog_version`) IN (%s)',
                implode(', ', $pairs)
            ),
            EmbeddingBacklogInterface::STATUS . ' IN (?)' => [
                Status::Pending->value,
                Status::Failed->value,
            ],
        ];
    }

    private function configureQuotedIdentifiers(
        AdapterInterface&MockObject $connection
    ): void {
        $connection->expects(self::exactly(2))
            ->method('quoteIdentifier')
            ->willReturnCallback(
                static fn (string $identifier): string => sprintf('`%s`', $identifier)
            );
    }
}
