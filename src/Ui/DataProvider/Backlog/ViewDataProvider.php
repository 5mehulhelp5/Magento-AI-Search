<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ui\DataProvider\Backlog;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class ViewDataProvider extends AbstractDataProvider
{
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $loadedData = null;

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        /** @var int|string|null $requestedBacklogId */
        $requestedBacklogId = $this->request->getParam($this->getRequestFieldName());
        $this->collection->addFieldToFilter(
            $this->getPrimaryFieldName(),
            (string) (int) $requestedBacklogId
        );

        $this->loadedData = [];

        /** @var \DavidBel\AiSearch\Model\EmbeddingBacklog $backlogItem */
        foreach ($this->collection->getItems() as $backlogItem) {
            /** @var int $backlogId */
            $backlogId = $backlogItem->getBacklogId();
            /** @var array<string, mixed> $backlogData */
            $backlogData = $backlogItem->getData();
            $this->loadedData[$backlogId] = $backlogData;
        }

        return $this->loadedData;
    }
}
