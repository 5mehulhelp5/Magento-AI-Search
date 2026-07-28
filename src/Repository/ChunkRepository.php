<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Repository;

use DavidBel\AiSearch\Api\ChunkRepositoryInterface;
use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\ChunkSearchResultsInterface;
use DavidBel\AiSearch\Repository\Chunk\DeleteById;
use DavidBel\AiSearch\Repository\Chunk\Get;
use DavidBel\AiSearch\Repository\Chunk\GetList;
use DavidBel\AiSearch\Repository\Chunk\Save;
use Magento\Framework\Api\SearchCriteriaInterface;

readonly class ChunkRepository implements ChunkRepositoryInterface
{
    public function __construct(
        private Save $save,
        private DeleteById $deleteById,
        private Get $get,
        private GetList $getList
    ) {
    }

    public function save(ChunkInterface $chunk): ChunkInterface
    {
        return $this->save->execute($chunk);
    }

    public function get(int $chunkId): ChunkInterface
    {
        return $this->get->execute($chunkId);
    }

    public function getList(SearchCriteriaInterface $searchCriteria): ChunkSearchResultsInterface
    {
        return $this->getList->execute($searchCriteria);
    }

    public function deleteById(int $chunkId): bool
    {
        return $this->deleteById->execute($chunkId);
    }
}
