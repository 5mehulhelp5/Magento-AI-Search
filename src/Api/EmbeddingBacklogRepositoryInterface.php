<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Api;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * Repository service contract for AI search embedding backlog entries.
 *
 * @api
 */
interface EmbeddingBacklogRepositoryInterface
{
    /**
     * Save an embedding backlog entry.
     *
     * @param \DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface $embeddingBacklog
     * @return \DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(EmbeddingBacklogInterface $embeddingBacklog): EmbeddingBacklogInterface;

    /**
     * Get an embedding backlog entry by ID.
     *
     * @param int $backlogId
     * @return \DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get(int $backlogId): EmbeddingBacklogInterface;

    /**
     * Get embedding backlog entries matching search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \DavidBel\AiSearch\Api\Data\EmbeddingBacklogSearchResultsInterface
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria
    ): EmbeddingBacklogSearchResultsInterface;

    /**
     * Delete an embedding backlog entry by ID.
     *
     * @param int $backlogId
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function deleteById(int $backlogId): bool;
}
