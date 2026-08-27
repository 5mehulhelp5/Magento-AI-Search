<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\ChunkSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use UnexpectedValueException;

class ChunkSearchResults implements ChunkSearchResultsInterface
{
    /**
     * @var list<ChunkInterface>
     */
    private array $items = [];

    private ?SearchCriteriaInterface $searchCriteria = null;

    private int $totalCount = 0;

    /**
     * @return list<ChunkInterface>
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
        $chunks = [];

        foreach ($items as $item) {
            if (!$item instanceof ChunkInterface) {
                throw new UnexpectedValueException('Chunk search results contain an invalid item.');
            }

            $chunks[] = $item;
        }

        $this->items = $chunks;
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
