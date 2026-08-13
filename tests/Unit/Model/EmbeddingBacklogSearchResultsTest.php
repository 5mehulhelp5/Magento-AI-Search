<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Model;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklogSearchResults;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class EmbeddingBacklogSearchResultsTest extends TestCase
{
    public function testStoresEmbeddingBacklogResults(): void
    {
        $embeddingBacklog = self::createStub(EmbeddingBacklogInterface::class);
        $searchCriteria = self::createStub(SearchCriteriaInterface::class);
        $searchResults = new EmbeddingBacklogSearchResults();

        $searchResults->setItems([$embeddingBacklog]);
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setTotalCount(1);

        self::assertSame([$embeddingBacklog], $searchResults->getItems());
        self::assertSame($searchCriteria, $searchResults->getSearchCriteria());
        self::assertSame(1, $searchResults->getTotalCount());
    }

    public function testRejectsAnInvalidEmbeddingBacklogResult(): void
    {
        $searchResults = new EmbeddingBacklogSearchResults();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Embedding backlog search results contain an invalid item.'
        );

        $searchResults->setItems([self::createStub(ChunkInterface::class)]);
    }

    public function testRequiresSearchCriteria(): void
    {
        $searchResults = new EmbeddingBacklogSearchResults();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Search criteria have not been set.');

        $searchResults->getSearchCriteria();
    }
}
