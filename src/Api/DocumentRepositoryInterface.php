<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Api;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\DocumentSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * Repository service contract for AI search documents.
 *
 * @api
 */
interface DocumentRepositoryInterface
{
    /**
     * Save a document.
     *
     * @param \DavidBel\AiSearch\Api\Data\DocumentInterface $document
     * @return \DavidBel\AiSearch\Api\Data\DocumentInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(DocumentInterface $document): DocumentInterface;

    /**
     * Get a document by ID.
     *
     * @param int $documentId
     * @return \DavidBel\AiSearch\Api\Data\DocumentInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get(int $documentId): DocumentInterface;

    /**
     * Get documents matching search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \DavidBel\AiSearch\Api\Data\DocumentSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): DocumentSearchResultsInterface;

    /**
     * Delete a document by ID.
     *
     * @param int $documentId
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function deleteById(int $documentId): bool;
}
