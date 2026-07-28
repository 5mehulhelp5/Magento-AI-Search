<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Search results for AI search document chunks.
 *
 * @api
 */
interface ChunkSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get the chunk items.
     *
     * @return \DavidBel\AiSearch\Api\Data\ChunkInterface[]
     */
    public function getItems(): array;

    /**
     * Set the chunk items.
     *
     * @param \Magento\Framework\Api\ExtensibleDataInterface[] $items
     * @return $this
     */
    public function setItems(array $items): static;
}
