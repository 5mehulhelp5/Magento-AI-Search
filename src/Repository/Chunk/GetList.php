<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Repository\Chunk;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\ChunkSearchResultsInterface;
use DavidBel\AiSearch\Model\ChunkSearchResultsFactory;
use DavidBel\AiSearch\Model\ResourceModel\Chunk\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use UnexpectedValueException;

class GetList
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly ChunkSearchResultsFactory $searchResultsFactory
    ) {
    }

    public function execute(SearchCriteriaInterface $searchCriteria): ChunkSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($this->getChunks($collection->getItems()));
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @param array<array-key, mixed> $items
     * @return list<ChunkInterface>
     */
    private function getChunks(array $items): array
    {
        $chunks = [];

        foreach ($items as $item) {
            if (!$item instanceof ChunkInterface) {
                throw new UnexpectedValueException('The chunk collection contains an invalid item.');
            }

            $chunks[] = $item;
        }

        return $chunks;
    }
}
