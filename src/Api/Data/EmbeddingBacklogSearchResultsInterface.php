<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Search results for AI search embedding backlog entries.
 *
 * @api
 */
interface EmbeddingBacklogSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get the embedding backlog items.
     *
     * @return \DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface[]
     */
    public function getItems(): array;

    /**
     * Set the embedding backlog items.
     *
     * @param \Magento\Framework\Api\ExtensibleDataInterface[] $items
     * @return $this
     */
    public function setItems(array $items): static;
}
