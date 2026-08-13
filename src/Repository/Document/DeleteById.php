<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Repository\Document;

use DavidBel\AiSearch\Model\ResourceModel\Document\CollectionFactory;
use Exception;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Model\AbstractModel;

class DeleteById
{
    public function __construct(
        private readonly Get $get,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function execute(int $documentId): bool
    {
        $document = $this->get->execute($documentId);

        if (!$document instanceof AbstractModel) {
            throw new CouldNotDeleteException(__('The document implementation cannot be deleted.'));
        }

        try {
            $this->collectionFactory->create()->getResourceModel()->delete($document);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(__('Could not delete the AI search document.'), $exception);
        }

        return true;
    }
}
