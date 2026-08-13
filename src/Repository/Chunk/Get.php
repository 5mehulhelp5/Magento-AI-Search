<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Repository\Chunk;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Model\ChunkFactory;
use DavidBel\AiSearch\Model\ResourceModel\Chunk\CollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;

class Get
{
    public function __construct(
        private readonly ChunkFactory $chunkFactory,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function execute(int $chunkId): ChunkInterface
    {
        $chunk = $this->chunkFactory->create();
        $this->collectionFactory->create()->getResourceModel()->load($chunk, $chunkId);

        if ($chunk->getChunkId() === null) {
            throw NoSuchEntityException::singleField(ChunkInterface::CHUNK_ID, $chunkId);
        }

        return $chunk;
    }
}
