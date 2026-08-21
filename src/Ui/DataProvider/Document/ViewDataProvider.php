<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ui\DataProvider\Document;

use DavidBel\AiSearch\Model\ResourceModel\Document\CollectionFactory;
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

        /** @var int|string|null $requestedDocumentId */
        $requestedDocumentId = $this->request->getParam($this->getRequestFieldName());
        $this->collection->addFieldToFilter(
            $this->getPrimaryFieldName(),
            (string) (int) $requestedDocumentId
        );

        $this->loadedData = [];

        /** @var \DavidBel\AiSearch\Model\Document $document */
        foreach ($this->collection->getItems() as $document) {
            /** @var int $documentId */
            $documentId = $document->getDocumentId();
            /** @var array<string, mixed> $documentData */
            $documentData = $document->getData();
            $this->loadedData[$documentId] = $documentData;
        }

        return $this->loadedData;
    }
}
