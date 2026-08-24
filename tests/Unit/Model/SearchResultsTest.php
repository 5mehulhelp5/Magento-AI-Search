<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Model;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Model\ChunkSearchResults;
use DavidBel\AiSearch\Model\DocumentSearchResults;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class SearchResultsTest extends TestCase
{
    public function testStoresDocumentResults(): void
    {
        $document = self::createStub(DocumentInterface::class);
        $searchCriteria = self::createStub(SearchCriteriaInterface::class);
        $searchResults = new DocumentSearchResults();

        $searchResults->setItems([$document]);
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setTotalCount(1);

        self::assertSame([$document], $searchResults->getItems());
        self::assertSame($searchCriteria, $searchResults->getSearchCriteria());
        self::assertSame(1, $searchResults->getTotalCount());
    }

    public function testRejectsAnInvalidDocumentResult(): void
    {
        $searchResults = new DocumentSearchResults();

        $this->expectException(UnexpectedValueException::class);

        $searchResults->setItems([self::createStub(ChunkInterface::class)]);
    }

    public function testStoresChunkResults(): void
    {
        $chunk = self::createStub(ChunkInterface::class);
        $searchCriteria = self::createStub(SearchCriteriaInterface::class);
        $searchResults = new ChunkSearchResults();

        $searchResults->setItems([$chunk]);
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setTotalCount(1);

        self::assertSame([$chunk], $searchResults->getItems());
        self::assertSame($searchCriteria, $searchResults->getSearchCriteria());
        self::assertSame(1, $searchResults->getTotalCount());
    }

    public function testRequiresSearchCriteria(): void
    {
        $searchResults = new ChunkSearchResults();

        $this->expectException(UnexpectedValueException::class);

        $searchResults->getSearchCriteria();
    }

    public function testDocumentResultsRequireSearchCriteria(): void
    {
        $this->expectException(UnexpectedValueException::class);

        (new DocumentSearchResults())->getSearchCriteria();
    }

    public function testRejectsAnInvalidChunkResult(): void
    {
        $this->expectException(UnexpectedValueException::class);

        (new ChunkSearchResults())->setItems([self::createStub(DocumentInterface::class)]);
    }
}
