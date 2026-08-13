<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Repository\EmbeddingBacklog;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogSearchResultsInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklogSearchResultsFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use UnexpectedValueException;

class GetList
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly EmbeddingBacklogSearchResultsFactory $searchResultsFactory
    ) {
    }

    public function execute(
        SearchCriteriaInterface $searchCriteria
    ): EmbeddingBacklogSearchResultsInterface {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($this->getEmbeddingBacklogs($collection->getItems()));
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @param array<array-key, mixed> $items
     * @return list<EmbeddingBacklogInterface>
     */
    private function getEmbeddingBacklogs(array $items): array
    {
        $embeddingBacklogs = [];

        foreach ($items as $item) {
            if (!$item instanceof EmbeddingBacklogInterface) {
                throw new UnexpectedValueException(
                    'The embedding backlog collection contains an invalid item.'
                );
            }

            $embeddingBacklogs[] = $item;
        }

        return $embeddingBacklogs;
    }
}
