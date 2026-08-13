<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\DocumentSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use UnexpectedValueException;

class DocumentSearchResults implements DocumentSearchResultsInterface
{
    /**
     * @var list<DocumentInterface>
     */
    private array $items = [];

    private ?SearchCriteriaInterface $searchCriteria = null;

    private int $totalCount = 0;

    /**
     * @return list<DocumentInterface>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param \Magento\Framework\Api\ExtensibleDataInterface[] $items
     */
    public function setItems(array $items): static
    {
        $documents = [];

        foreach ($items as $item) {
            if (!$item instanceof DocumentInterface) {
                throw new UnexpectedValueException('Document search results contain an invalid item.');
            }

            $documents[] = $item;
        }

        $this->items = $documents;
        return $this;
    }

    public function getSearchCriteria(): SearchCriteriaInterface
    {
        if ($this->searchCriteria === null) {
            throw new UnexpectedValueException('Search criteria have not been set.');
        }

        return $this->searchCriteria;
    }

    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria): static
    {
        $this->searchCriteria = $searchCriteria;

        return $this;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function setTotalCount(mixed $totalCount): static
    {
        $this->totalCount = $totalCount;

        return $this;
    }
}
