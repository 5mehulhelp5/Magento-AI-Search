<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Model\ResourceModel;

use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Maintenance;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use InvalidArgumentException;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EmbeddingBacklogAdditionalTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    /**
     * @param array{int, int, int, ?string, ?int} $arguments
     */
    #[DataProvider('invalidDeleteQueries')]
    public function testRejectsInvalidDeleteQueryArguments(array $arguments, string $message): void
    {
        $resource = $this->getMockBuilder(EmbeddingBacklog::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection'])
            ->getMock();
        $resource->expects(self::never())->method('getConnection');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $resource->getItemsToDelete(...$arguments);
    }

    /**
     * @return array<string, array{array{int, int, int, ?string, ?int}, string}>
     */
    public static function invalidDeleteQueries(): array
    {
        return [
            'index version' => [[0, 10, 3, null, null], 'index version must be positive'],
            'limit' => [[1, 0, 3, null, null], 'batch limit must be positive'],
            'threshold' => [[1, 10, 0, null, null], 'threshold must be positive'],
            'timestamp only' => [[1, 10, 3, 'time', null], 'cursor values must be provided'],
            'id only' => [[1, 10, 3, null, 1], 'cursor values must be provided'],
            'negative cursor' => [[1, 10, 3, 'time', -1], 'cursor ID must be non-negative'],
        ];
    }

    public function testLoadsDeleteItemsWithoutBlockingUpserts(): void
    {
        $blocking = $this->blockingSelect();
        $select = $this->deleteSelect();
        $rows = [['backlog_id' => 10, 'backlog_version' => 2]];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::exactly(2))
            ->method('select')
            ->willReturnOnConsecutiveCalls($blocking, $select);
        $connection->expects(self::exactly(3))
            ->method('quoteInto')
            ->willReturnOnConsecutiveCalls('pending', 'failed', 'attempt-limit');
        $connection->expects(self::once())->method('fetchAll')->with($select)->willReturn($rows);

        self::assertSame(
            $rows,
            $this->createResource($connection)->getItemsToDelete(2, 100, 3)
        );
    }

    public function testLoadsDeleteItemsAfterCursor(): void
    {
        $blocking = $this->blockingSelect();
        $select = $this->deleteSelect(5);
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::exactly(2))
            ->method('select')
            ->willReturnOnConsecutiveCalls($blocking, $select);
        $connection->expects(self::exactly(6))
            ->method('quoteInto')
            ->willReturnOnConsecutiveCalls(
                'pending',
                'failed',
                'attempt-limit',
                'after-time',
                'at-time',
                'after-id'
            );
        $connection->method('fetchAll')->willReturn([]);

        self::assertSame(
            [],
            $this->createResource($connection)->getItemsToDelete(2, 100, 3, 'time', 10)
        );
    }

    public function testMarksMissingChunkUpsertsOutdated(): void
    {
        $select = $this->createMock(Select::class);
        $select->expects(self::once())->method('joinLeft')->willReturnSelf();
        $select->expects(self::once())->method('columns')->willReturnSelf();
        $select->expects(self::exactly(4))->method('where')->willReturnSelf();
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('rowCount')->willReturn(3);
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('select')->willReturn($select);
        $connection->expects(self::once())
            ->method('quoteInto')
            ->with('?', Status::Outdated->value)
            ->willReturn(Status::Outdated->value);
        $connection->expects(self::once())
            ->method('updateFromSelect')
            ->with($select, ['backlog' => 'embedding_backlog'])
            ->willReturn('update query');
        $connection->expects(self::once())
            ->method('query')
            ->with('update query')
            ->willReturn($statement);

        self::assertSame(3, $this->createMaintenance($connection)->markMissingChunkUpsertsOutdated(2));
    }

    public function testCleanupCanProtectCurrentIndexVersion(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::exactly(5))
            ->method('quoteInto')
            ->willReturnOnConsecutiveCalls(
                'failed',
                'attempt-limit',
                'result',
                'expired',
                'protected'
            );
        $connection->expects(self::once())
            ->method('delete')
            ->with(
                'embedding_backlog',
                '(((failed AND attempt-limit) OR (result AND expired))) AND protected'
            )
            ->willReturn(2);

        self::assertSame(
            2,
            $this->createMaintenance($connection)
                ->deleteExhaustedUpsertsOrExpiredResults(3, 'time', 2)
        );
    }

    /**
     * @param array{int, string, ?int} $arguments
     */
    #[DataProvider('invalidCleanupArguments')]
    public function testCleanupRejectsInvalidArguments(array $arguments, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new Maintenance(self::createStub(CollectionFactory::class)))
            ->deleteExhaustedUpsertsOrExpiredResults(...$arguments);
    }

    /**
     * @return array<string, array{array{int, string, ?int}, string}>
     */
    public static function invalidCleanupArguments(): array
    {
        return [
            'threshold' => [[0, 'time', null], 'threshold must be positive'],
            'cutoff' => [[3, '', null], 'cutoff must not be empty'],
            'protected version' => [[3, 'time', 0], 'index version must be positive'],
        ];
    }

    public function testMissingChunkCleanupRejectsInvalidIndexVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Maintenance(self::createStub(CollectionFactory::class)))
            ->markMissingChunkUpsertsOutdated(0);
    }

    private function blockingSelect(): Select&MockObject
    {
        $select = $this->createMock(Select::class);
        $select->expects(self::once())->method('from')->willReturnSelf();
        $select->expects(self::once())->method('columns')->willReturnSelf();
        $select->expects(self::exactly(4))->method('where')->willReturnSelf();
        $select->expects(self::once())->method('assemble')->willReturn('blocking query');

        return $select;
    }

    private function deleteSelect(int $whereCount = 4): Select&MockObject
    {
        $select = $this->createMock(Select::class);
        $select->expects(self::once())->method('from')->willReturnSelf();
        $select->expects(self::exactly($whereCount))->method('where')->willReturnSelf();
        $select->expects(self::once())->method('order')->willReturnSelf();
        $select->expects(self::once())->method('limit')->willReturnSelf();

        return $select;
    }

    private function createResource(AdapterInterface $connection): EmbeddingBacklog
    {
        $resource = $this->getMockBuilder(EmbeddingBacklog::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $resource->expects(self::once())->method('getConnection')->willReturn($connection);
        $resource->expects(self::exactly(2))
            ->method('getMainTable')
            ->willReturn('embedding_backlog');

        return $resource;
    }

    private function createMaintenance(AdapterInterface $connection): Maintenance
    {
        $resource = self::createStub(EmbeddingBacklog::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('embedding_backlog');
        $resource->method('getTable')->willReturn('chunk_table');
        $collection = self::createStub(Collection::class);
        $collection->method('getResourceModel')->willReturn($resource);
        $factory = self::createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new Maintenance($factory);
    }
}
