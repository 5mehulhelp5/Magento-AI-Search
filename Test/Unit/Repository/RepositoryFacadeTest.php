<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Repository;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\ChunkSearchResultsInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\DocumentSearchResultsInterface;
use DavidBel\AiSearch\Repository\Chunk\DeleteById as ChunkDeleteById;
use DavidBel\AiSearch\Repository\Chunk\Get as ChunkGet;
use DavidBel\AiSearch\Repository\Chunk\GetList as ChunkGetList;
use DavidBel\AiSearch\Repository\Chunk\Save as ChunkSave;
use DavidBel\AiSearch\Repository\ChunkRepository;
use DavidBel\AiSearch\Repository\Document\DeleteById as DocumentDeleteById;
use DavidBel\AiSearch\Repository\Document\Get as DocumentGet;
use DavidBel\AiSearch\Repository\Document\GetList as DocumentGetList;
use DavidBel\AiSearch\Repository\Document\Save as DocumentSave;
use DavidBel\AiSearch\Repository\DocumentRepository;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\TestCase;

class RepositoryFacadeTest extends TestCase
{
    public function testChunkRepositoryDelegatesEveryOperation(): void
    {
        $chunk = self::createStub(ChunkInterface::class);
        $criteria = self::createStub(SearchCriteriaInterface::class);
        $results = self::createStub(ChunkSearchResultsInterface::class);
        $save = self::createStub(ChunkSave::class);
        $save->method('execute')->willReturn($chunk);
        $delete = self::createStub(ChunkDeleteById::class);
        $delete->method('execute')->willReturn(true);
        $get = self::createStub(ChunkGet::class);
        $get->method('execute')->willReturn($chunk);
        $getList = self::createStub(ChunkGetList::class);
        $getList->method('execute')->willReturn($results);
        $repository = new ChunkRepository($save, $delete, $get, $getList);

        self::assertSame($chunk, $repository->save($chunk));
        self::assertSame($chunk, $repository->get(10));
        self::assertSame($results, $repository->getList($criteria));
        self::assertTrue($repository->deleteById(10));
    }

    public function testDocumentRepositoryDelegatesEveryOperation(): void
    {
        $document = self::createStub(DocumentInterface::class);
        $criteria = self::createStub(SearchCriteriaInterface::class);
        $results = self::createStub(DocumentSearchResultsInterface::class);
        $save = self::createStub(DocumentSave::class);
        $save->method('execute')->willReturn($document);
        $delete = self::createStub(DocumentDeleteById::class);
        $delete->method('execute')->willReturn(true);
        $get = self::createStub(DocumentGet::class);
        $get->method('execute')->willReturn($document);
        $getList = self::createStub(DocumentGetList::class);
        $getList->method('execute')->willReturn($results);
        $repository = new DocumentRepository($save, $delete, $get, $getList);

        self::assertSame($document, $repository->save($document));
        self::assertSame($document, $repository->get(10));
        self::assertSame($results, $repository->getList($criteria));
        self::assertTrue($repository->deleteById(10));
    }
}
