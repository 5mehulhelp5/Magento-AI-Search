<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Repository\Chunk;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Model\ResourceModel\Chunk\CollectionFactory;
use Exception;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Model\AbstractModel;

class Save
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function execute(ChunkInterface $chunk): ChunkInterface
    {
        if (!$chunk instanceof AbstractModel) {
            throw new CouldNotSaveException(__('The chunk implementation cannot be persisted.'));
        }

        try {
            $this->collectionFactory->create()->getResourceModel()->save($chunk);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(__('Could not save the AI search chunk.'), $exception);
        }

        return $chunk;
    }
}
