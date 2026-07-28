<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Repository;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\DocumentSearchResultsInterface;
use DavidBel\AiSearch\Api\DocumentRepositoryInterface;
use DavidBel\AiSearch\Repository\Document\DeleteById;
use DavidBel\AiSearch\Repository\Document\Get;
use DavidBel\AiSearch\Repository\Document\GetList;
use DavidBel\AiSearch\Repository\Document\Save;
use Magento\Framework\Api\SearchCriteriaInterface;

readonly class DocumentRepository implements DocumentRepositoryInterface
{
    public function __construct(
        private Save $save,
        private DeleteById $deleteById,
        private Get $get,
        private GetList $getList
    ) {
    }

    public function save(DocumentInterface $document): DocumentInterface
    {
        return $this->save->execute($document);
    }

    public function get(int $documentId): DocumentInterface
    {
        return $this->get->execute($documentId);
    }

    public function getList(SearchCriteriaInterface $searchCriteria): DocumentSearchResultsInterface
    {
        return $this->getList->execute($searchCriteria);
    }

    public function deleteById(int $documentId): bool
    {
        return $this->deleteById->execute($documentId);
    }
}
