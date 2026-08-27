<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Model\ResourceModel;

use DavidBel\AiSearch\Indexer\ProductIndexer;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Progress as BacklogProgress;
use DavidBel\AiSearch\Model\ResourceModel\ProductIndexer\Progress as ProductProgress;
use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class ProgressTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    public function testBacklogProgressBuildsQueryConvertsRowsAndCachesResource(): void
    {
        $select = $this->createMock(Select::class);
        $select->expects(self::exactly(2))->method('from')->willReturnSelf();
        $select->expects(self::exactly(2))->method('where')->willReturnSelf();
        $select->expects(self::exactly(2))->method('group')->willReturnSelf();
        $select->expects(self::exactly(2))->method('order')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::exactly(2))->method('select')->willReturn($select);
        $connection->expects(self::exactly(2))->method('fetchAll')->with($select)->willReturn([
            [
                'operation' => 'upsert',
                'index_version' => '4',
                'full_reindex_status' => '1',
                'status' => 'pending',
                'item_count' => '7',
            ],
        ]);
        $resource = $this->createMock(EmbeddingBacklog::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('embedding_backlog');
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('getResourceModel')->willReturn($resource);
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($collection);
        $progress = new BacklogProgress($factory);
        $expected = [[
            'operation' => 'upsert',
            'index_version' => 4,
            'full_reindex_status' => 1,
            'status' => 'pending',
            'item_count' => 7,
        ]];

        self::assertSame($expected, $progress->getItemCounts());
        self::assertSame($expected, $progress->getItemCounts());
    }

    public function testProductProgressCountsDistinctQueuedProducts(): void
    {
        $versionSelect = $this->createMock(Select::class);
        $versionSelect->expects(self::once())->method('from')->willReturnSelf();
        $versionSelect->expects(self::once())->method('where')->willReturnSelf();
        $versionSelect->expects(self::once())->method('limit')->with(1)->willReturnSelf();
        $queueSelect = $this->createMock(Select::class);
        $queueSelect->expects(self::once())->method('from')->willReturnSelf();
        $queueSelect->expects(self::once())
            ->method('where')
            ->with('version_id > ?', 25)
            ->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::exactly(2))
            ->method('select')
            ->willReturnOnConsecutiveCalls($versionSelect, $queueSelect);
        $connection->expects(self::exactly(2))
            ->method('fetchOne')
            ->willReturnMap([
                [$versionSelect, [], '25'],
                [$queueSelect, [], '8'],
            ]);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnMap([
            ['mview_state', 'mview_state'],
            [ProductIndexer::VIEW_ID . '_cl', 'product_ai_search_cl'],
        ]);

        self::assertSame(8, (new ProductProgress($resourceConnection))->getQueuedProductCount());
    }
}
