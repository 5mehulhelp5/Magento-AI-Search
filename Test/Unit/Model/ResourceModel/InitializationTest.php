<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Model\ResourceModel;

use DavidBel\AiSearch\Model\ResourceModel\Chunk as ChunkResource;
use DavidBel\AiSearch\Model\ResourceModel\Chunk\Collection as ChunkCollection;
use DavidBel\AiSearch\Model\ResourceModel\Document as DocumentResource;
use DavidBel\AiSearch\Model\ResourceModel\Document\Collection as DocumentCollection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as BacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection as BacklogCollection;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Model\ResourceModel\Db\Context;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class InitializationTest extends TestCase
{
    public function testResourceModelsDeclareTablesAndIdentifiers(): void
    {
        $context = self::createStub(Context::class);
        $chunk = new ChunkResource($context);
        $document = new DocumentResource($context);
        $backlog = new BacklogResource($context);

        self::assertSame('chunk_id', $chunk->getIdFieldName());
        self::assertSame('document_id', $document->getIdFieldName());
        self::assertSame('backlog_id', $backlog->getIdFieldName());
    }

    public function testCollectionsExposeTheirResourceModels(): void
    {
        $chunkResource = self::createStub(ChunkResource::class);
        $documentResource = self::createStub(DocumentResource::class);
        $backlogResource = self::createStub(BacklogResource::class);

        self::assertSame(
            $chunkResource,
            $this->collection(ChunkCollection::class, $chunkResource)->getResourceModel()
        );
        self::assertSame(
            $documentResource,
            $this->collection(DocumentCollection::class, $documentResource)->getResourceModel()
        );
        self::assertSame(
            $backlogResource,
            $this->collection(BacklogCollection::class, $backlogResource)->getResourceModel()
        );
    }

    /**
     * @template TCollection of AbstractCollection
     * @param class-string<TCollection> $collectionClass
     * @param AbstractDb&\PHPUnit\Framework\MockObject\Stub $resource
     * @return TCollection
     */
    private function collection(string $collectionClass, AbstractDb $resource): AbstractCollection
    {
        $select = self::createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $connection = self::createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('test_table');

        return new $collectionClass(
            self::createStub(EntityFactoryInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(FetchStrategyInterface::class),
            self::createStub(ManagerInterface::class),
            $connection,
            $resource
        );
    }
}
