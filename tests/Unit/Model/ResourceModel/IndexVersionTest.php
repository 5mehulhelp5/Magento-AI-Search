<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Model\ResourceModel;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklog\FullReindexStatus;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\IndexVersion;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use InvalidArgumentException;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class IndexVersionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    public function testDoesNotLoadResourceForEmptyIndexedItems(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::never())->method('create');

        (new IndexVersion($factory))->markFullReindexItemsIndexed([]);
    }

    public function testMarksMatchingPendingItemsIndexed(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('quoteIdentifier')->willReturnMap([
            [EmbeddingBacklogInterface::BACKLOG_ID, 'backlog_id'],
            [EmbeddingBacklogInterface::INDEX_VERSION, 'index_version'],
        ]);
        $connection->expects(self::once())
            ->method('update')
            ->with(
                'embedding_backlog',
                [
                    EmbeddingBacklogInterface::FULL_REINDEX_STATUS =>
                        FullReindexStatus::Indexed->value,
                ],
                [
                    '(backlog_id, index_version) IN ((10, 2), (20, 3))',
                    EmbeddingBacklogInterface::FULL_REINDEX_STATUS . ' = ?' =>
                        FullReindexStatus::Pending->value,
                ]
            );

        $this->createIndexVersion($connection)->markFullReindexItemsIndexed([10 => 2, 20 => 3]);
    }

    public function testMarksLargeSetsInBatches(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('quoteIdentifier')->willReturnArgument(0);
        $connection->expects(self::exactly(2))->method('update');
        $versions = array_fill(1, 1_001, 2);

        $this->createIndexVersion($connection)->markFullReindexItemsIndexed($versions);
    }

    public function testReturnsFullReindexProgress(): void
    {
        $connection = $this->createProgressConnection([
            'total' => '10',
            'indexed' => 8,
            'unfinished' => '1',
        ]);

        self::assertSame(
            ['total' => 10, 'indexed' => 8, 'unfinished' => 1],
            $this->createIndexVersion($connection)->getFullReindexProgress(2)
        );
    }

    public function testRejectsNonArrayProgressResult(): void
    {
        $connection = $this->createProgressConnection(false);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('invalid result');

        $this->createIndexVersion($connection)->getFullReindexProgress(2);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    #[DataProvider('invalidProgressRows')]
    public function testRejectsInvalidProgressFields(array $row, string $field): void
    {
        $connection = $this->createProgressConnection($row);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(sprintf('field "%s" is invalid', $field));

        $this->createIndexVersion($connection)->getFullReindexProgress(2);
    }

    /**
     * @return array<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidProgressRows(): array
    {
        return [
            'missing total' => [['indexed' => 0, 'unfinished' => 0], 'total'],
            'negative indexed' => [['total' => 1, 'indexed' => -1, 'unfinished' => 0], 'indexed'],
            'invalid unfinished' => [['total' => 1, 'indexed' => 0, 'unfinished' => 'x'], 'unfinished'],
        ];
    }

    public function testDeletesItemsOutsideIndexVersion(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())
            ->method('quoteInto')
            ->with(EmbeddingBacklogInterface::INDEX_VERSION . ' <> ?', 2)
            ->willReturn('index_version <> 2');
        $connection->expects(self::once())
            ->method('delete')
            ->with('embedding_backlog', 'index_version <> 2')
            ->willReturn(4);

        self::assertSame(4, $this->createIndexVersion($connection)->deleteItemsOutsideIndexVersion(2));
    }

    #[DataProvider('invalidIndexVersions')]
    public function testRejectsInvalidIndexVersion(int $indexVersion): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::never())->method('create');
        $provider = new IndexVersion($factory);

        $this->expectException(InvalidArgumentException::class);

        $provider->getFullReindexProgress($indexVersion);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidIndexVersions(): array
    {
        return ['zero' => [0], 'negative' => [-1]];
    }

    private function createProgressConnection(mixed $row): AdapterInterface
    {
        $select = self::createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = self::createStub(AdapterInterface::class);
        $connection->method('quoteInto')->willReturn('?');
        $connection->method('select')->willReturn($select);
        $connection->method('fetchRow')->willReturn($row);

        return $connection;
    }

    private function createIndexVersion(AdapterInterface $connection): IndexVersion
    {
        $resource = self::createStub(EmbeddingBacklog::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('embedding_backlog');
        $collection = self::createStub(Collection::class);
        $collection->method('getResourceModel')->willReturn($resource);
        $factory = self::createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new IndexVersion($factory);
    }
}
