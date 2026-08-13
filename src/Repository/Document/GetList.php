<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Repository\Document;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\DocumentSearchResultsInterface;
use DavidBel\AiSearch\Model\DocumentSearchResultsFactory;
use DavidBel\AiSearch\Model\ResourceModel\Document\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use UnexpectedValueException;

class GetList
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly DocumentSearchResultsFactory $searchResultsFactory
    ) {
    }

    public function execute(SearchCriteriaInterface $searchCriteria): DocumentSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($this->getDocuments($collection->getItems()));
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @param array<array-key, mixed> $items
     * @return list<DocumentInterface>
     */
    private function getDocuments(array $items): array
    {
        $documents = [];

        foreach ($items as $item) {
            if (!$item instanceof DocumentInterface) {
                throw new UnexpectedValueException('The document collection contains an invalid item.');
            }

            $documents[] = $item;
        }

        return $documents;
    }
}
