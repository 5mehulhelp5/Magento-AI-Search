<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Repository;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogSearchResultsInterface;
use DavidBel\AiSearch\Api\EmbeddingBacklogRepositoryInterface;
use DavidBel\AiSearch\Repository\EmbeddingBacklog\DeleteById;
use DavidBel\AiSearch\Repository\EmbeddingBacklog\Get;
use DavidBel\AiSearch\Repository\EmbeddingBacklog\GetList;
use DavidBel\AiSearch\Repository\EmbeddingBacklog\Save;
use Magento\Framework\Api\SearchCriteriaInterface;

class EmbeddingBacklogRepository implements EmbeddingBacklogRepositoryInterface
{
    public function __construct(
        private readonly Save $save,
        private readonly DeleteById $deleteById,
        private readonly Get $get,
        private readonly GetList $getList
    ) {
    }

    public function save(EmbeddingBacklogInterface $embeddingBacklog): EmbeddingBacklogInterface
    {
        return $this->save->execute($embeddingBacklog);
    }

    public function get(int $backlogId): EmbeddingBacklogInterface
    {
        return $this->get->execute($backlogId);
    }

    public function getList(
        SearchCriteriaInterface $searchCriteria
    ): EmbeddingBacklogSearchResultsInterface {
        return $this->getList->execute($searchCriteria);
    }

    public function deleteById(int $backlogId): bool
    {
        return $this->deleteById->execute($backlogId);
    }
}
