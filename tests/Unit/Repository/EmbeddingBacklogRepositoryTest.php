<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Repository;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogSearchResultsInterface;
use DavidBel\AiSearch\Repository\EmbeddingBacklog\DeleteById;
use DavidBel\AiSearch\Repository\EmbeddingBacklog\Get;
use DavidBel\AiSearch\Repository\EmbeddingBacklog\GetList;
use DavidBel\AiSearch\Repository\EmbeddingBacklog\Save;
use DavidBel\AiSearch\Repository\EmbeddingBacklogRepository;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\TestCase;

class EmbeddingBacklogRepositoryTest extends TestCase
{
    public function testDelegatesRepositoryOperations(): void
    {
        $embeddingBacklog = self::createStub(EmbeddingBacklogInterface::class);
        $searchCriteria = self::createStub(SearchCriteriaInterface::class);
        $searchResults = self::createStub(
            EmbeddingBacklogSearchResultsInterface::class
        );
        $save = $this->createMock(Save::class);
        $save->expects(self::once())
            ->method('execute')
            ->with($embeddingBacklog)
            ->willReturn($embeddingBacklog);
        $get = $this->createMock(Get::class);
        $get->expects(self::once())
            ->method('execute')
            ->with(12)
            ->willReturn($embeddingBacklog);
        $getList = $this->createMock(GetList::class);
        $getList->expects(self::once())
            ->method('execute')
            ->with($searchCriteria)
            ->willReturn($searchResults);
        $deleteById = $this->createMock(DeleteById::class);
        $deleteById->expects(self::once())
            ->method('execute')
            ->with(12)
            ->willReturn(true);
        $repository = new EmbeddingBacklogRepository(
            $save,
            $deleteById,
            $get,
            $getList
        );

        self::assertSame($embeddingBacklog, $repository->save($embeddingBacklog));
        self::assertSame($embeddingBacklog, $repository->get(12));
        self::assertSame($searchResults, $repository->getList($searchCriteria));
        self::assertTrue($repository->deleteById(12));
    }
}
