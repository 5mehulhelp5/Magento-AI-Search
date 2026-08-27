<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Repository\EmbeddingBacklog;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklogSearchResults;
use DavidBel\AiSearch\Model\EmbeddingBacklogSearchResultsFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Repository\EmbeddingBacklog\GetList;
use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnexpectedValueException;

class GetListTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            CollectionFactory::class,
            EmbeddingBacklogSearchResultsFactory::class
        );
    }

    public function testBuildsSearchResultsFromTheProcessedCollection(): void
    {
        $embeddingBacklog = self::createStub(EmbeddingBacklogInterface::class);
        $searchCriteria = self::createStub(SearchCriteriaInterface::class);
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())
            ->method('getItems')
            ->willReturn([12 => $embeddingBacklog]);
        $collection->expects(self::once())
            ->method('getSize')
            ->willReturn(1);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::once())
            ->method('create')
            ->willReturn($collection);
        $collectionProcessor = $this->createMock(CollectionProcessorInterface::class);
        $collectionProcessor->expects(self::once())
            ->method('process')
            ->with($searchCriteria, $collection);
        $searchResults = new EmbeddingBacklogSearchResults();
        $searchResultsFactory = $this->createMock(
            EmbeddingBacklogSearchResultsFactory::class
        );
        $searchResultsFactory->expects(self::once())
            ->method('create')
            ->willReturn($searchResults);

        $result = (new GetList(
            $collectionFactory,
            $collectionProcessor,
            $searchResultsFactory
        ))->execute($searchCriteria);

        self::assertSame($searchResults, $result);
        self::assertSame([$embeddingBacklog], $result->getItems());
        self::assertSame($searchCriteria, $result->getSearchCriteria());
        self::assertSame(1, $result->getTotalCount());
    }

    public function testRejectsAnInvalidCollectionItem(): void
    {
        $searchCriteria = self::createStub(SearchCriteriaInterface::class);
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())
            ->method('getItems')
            ->willReturn([new stdClass()]);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::once())
            ->method('create')
            ->willReturn($collection);
        $collectionProcessor = $this->createMock(CollectionProcessorInterface::class);
        $collectionProcessor->expects(self::once())
            ->method('process')
            ->with($searchCriteria, $collection);
        $searchResultsFactory = $this->createMock(
            EmbeddingBacklogSearchResultsFactory::class
        );
        $searchResultsFactory->expects(self::once())
            ->method('create')
            ->willReturn(new EmbeddingBacklogSearchResults());

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'The embedding backlog collection contains an invalid item.'
        );

        (new GetList(
            $collectionFactory,
            $collectionProcessor,
            $searchResultsFactory
        ))->execute($searchCriteria);
    }
}
