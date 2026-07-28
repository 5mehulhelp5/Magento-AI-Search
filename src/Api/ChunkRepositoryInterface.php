<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Api;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\ChunkSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * Repository service contract for AI search document chunks.
 *
 * @api
 */
interface ChunkRepositoryInterface
{
    /**
     * Save a chunk.
     *
     * @param \DavidBel\AiSearch\Api\Data\ChunkInterface $chunk
     * @return \DavidBel\AiSearch\Api\Data\ChunkInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(ChunkInterface $chunk): ChunkInterface;

    /**
     * Get a chunk by ID.
     *
     * @param int $chunkId
     * @return \DavidBel\AiSearch\Api\Data\ChunkInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get(int $chunkId): ChunkInterface;

    /**
     * Get chunks matching search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \DavidBel\AiSearch\Api\Data\ChunkSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ChunkSearchResultsInterface;

    /**
     * Delete a chunk by ID.
     *
     * @param int $chunkId
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function deleteById(int $chunkId): bool;
}
