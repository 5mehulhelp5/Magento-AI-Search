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
 * Search results for AI search documents.
 *
 * @api
 */
interface DocumentSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get the document items.
     *
     * @return \DavidBel\AiSearch\Api\Data\DocumentInterface[]
     */
    public function getItems(): array;

    /**
     * Set the document items.
     *
     * @param \Magento\Framework\Api\ExtensibleDataInterface[] $items
     * @return $this
     */
    public function setItems(array $items): static;
}
