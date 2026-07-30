<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Repository\Chunk;

use DavidBel\AiSearch\Model\ResourceModel\Chunk\CollectionFactory;
use Exception;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Model\AbstractModel;

readonly class DeleteById
{
    public function __construct(
        private Get $get,
        private CollectionFactory $collectionFactory
    ) {
    }

    public function execute(int $chunkId): bool
    {
        $chunk = $this->get->execute($chunkId);

        if (!$chunk instanceof AbstractModel) {
            throw new CouldNotDeleteException(__('The chunk implementation cannot be deleted.'));
        }

        try {
            $this->collectionFactory->create()->getResourceModel()->delete($chunk);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(__('Could not delete the AI search chunk.'), $exception);
        }

        return true;
    }
}
