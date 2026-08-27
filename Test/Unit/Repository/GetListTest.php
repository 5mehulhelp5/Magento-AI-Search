<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Repository;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Model\ChunkSearchResults;
use DavidBel\AiSearch\Model\ChunkSearchResultsFactory;
use DavidBel\AiSearch\Model\DocumentSearchResults;
use DavidBel\AiSearch\Model\DocumentSearchResultsFactory;
use DavidBel\AiSearch\Model\ResourceModel\Chunk\Collection as ChunkCollection;
use DavidBel\AiSearch\Model\ResourceModel\Chunk\CollectionFactory as ChunkCollectionFactory;
use DavidBel\AiSearch\Model\ResourceModel\Document\Collection as DocumentCollection;
use DavidBel\AiSearch\Model\ResourceModel\Document\CollectionFactory as DocumentCollectionFactory;
use DavidBel\AiSearch\Repository\Chunk\GetList as ChunkGetList;
use DavidBel\AiSearch\Repository\Document\GetList as DocumentGetList;
use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnexpectedValueException;

class GetListTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            ChunkCollectionFactory::class,
            DocumentCollectionFactory::class,
            ChunkSearchResultsFactory::class,
            DocumentSearchResultsFactory::class
        );
    }

    public function testReturnsDocumentSearchResults(): void
    {
        $document = self::createStub(DocumentInterface::class);
        $criteria = self::createStub(SearchCriteriaInterface::class);
        $collection = $this->createMock(DocumentCollection::class);
        $collection->expects(self::once())->method('getItems')->willReturn([$document]);
        $collection->expects(self::once())->method('getSize')->willReturn(1);
        $processor = $this->createMock(CollectionProcessorInterface::class);
        $processor->expects(self::once())->method('process')->with($criteria, $collection);
        $results = new DocumentSearchResults();
        $factory = self::createStub(DocumentSearchResultsFactory::class);
        $factory->method('create')->willReturn($results);
        $collectionFactory = self::createStub(DocumentCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        self::assertSame(
            $results,
            (new DocumentGetList($collectionFactory, $processor, $factory))->execute($criteria)
        );
        self::assertSame([$document], $results->getItems());
        self::assertSame($criteria, $results->getSearchCriteria());
        self::assertSame(1, $results->getTotalCount());
    }

    public function testReturnsChunkSearchResults(): void
    {
        $chunk = self::createStub(ChunkInterface::class);
        $criteria = self::createStub(SearchCriteriaInterface::class);
        $collection = $this->createMock(ChunkCollection::class);
        $collection->expects(self::once())->method('getItems')->willReturn([$chunk]);
        $collection->expects(self::once())->method('getSize')->willReturn(1);
        $processor = $this->createMock(CollectionProcessorInterface::class);
        $processor->expects(self::once())->method('process')->with($criteria, $collection);
        $results = new ChunkSearchResults();
        $factory = self::createStub(ChunkSearchResultsFactory::class);
        $factory->method('create')->willReturn($results);
        $collectionFactory = self::createStub(ChunkCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        self::assertSame(
            $results,
            (new ChunkGetList($collectionFactory, $processor, $factory))->execute($criteria)
        );
        self::assertSame([$chunk], $results->getItems());
        self::assertSame($criteria, $results->getSearchCriteria());
        self::assertSame(1, $results->getTotalCount());
    }

    #[DataProvider('invalidCollectionTypes')]
    public function testRejectsInvalidCollectionItems(string $type): void
    {
        $criteria = self::createStub(SearchCriteriaInterface::class);
        $processor = self::createStub(CollectionProcessorInterface::class);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('collection contains an invalid item');

        if ($type === 'document') {
            $collection = self::createStub(DocumentCollection::class);
            $collection->method('getItems')->willReturn([new stdClass()]);
            $collectionFactory = self::createStub(DocumentCollectionFactory::class);
            $collectionFactory->method('create')->willReturn($collection);
            $resultsFactory = self::createStub(DocumentSearchResultsFactory::class);
            $resultsFactory->method('create')->willReturn(new DocumentSearchResults());
            (new DocumentGetList($collectionFactory, $processor, $resultsFactory))
                ->execute($criteria);

            return;
        }

        $collection = self::createStub(ChunkCollection::class);
        $collection->method('getItems')->willReturn([new stdClass()]);
        $collectionFactory = self::createStub(ChunkCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);
        $resultsFactory = self::createStub(ChunkSearchResultsFactory::class);
        $resultsFactory->method('create')->willReturn(new ChunkSearchResults());
        (new ChunkGetList($collectionFactory, $processor, $resultsFactory))->execute($criteria);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCollectionTypes(): array
    {
        return ['document' => ['document'], 'chunk' => ['chunk']];
    }
}
