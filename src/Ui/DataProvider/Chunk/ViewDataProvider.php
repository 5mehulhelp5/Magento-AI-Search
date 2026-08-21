<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ui\DataProvider\Chunk;

use DavidBel\AiSearch\Model\ResourceModel\Chunk\CollectionFactory;
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

        /** @var int|string|null $requestedChunkId */
        $requestedChunkId = $this->request->getParam($this->getRequestFieldName());
        $this->collection->addFieldToFilter(
            $this->getPrimaryFieldName(),
            (string) (int) $requestedChunkId
        );

        $this->loadedData = [];

        /** @var \DavidBel\AiSearch\Model\Chunk $chunk */
        foreach ($this->collection->getItems() as $chunk) {
            /** @var int $chunkId */
            $chunkId = $chunk->getChunkId();
            /** @var array<string, mixed> $chunkData */
            $chunkData = $chunk->getData();
            $this->loadedData[$chunkId] = $chunkData;
        }

        return $this->loadedData;
    }
}
