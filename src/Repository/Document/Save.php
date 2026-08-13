<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Repository\Document;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Model\ResourceModel\Document\CollectionFactory;
use Exception;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Model\AbstractModel;

class Save
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function execute(DocumentInterface $document): DocumentInterface
    {
        if (!$document instanceof AbstractModel) {
            throw new CouldNotSaveException(__('The document implementation cannot be persisted.'));
        }

        try {
            $this->collectionFactory->create()->getResourceModel()->save($document);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(__('Could not save the AI search document.'), $exception);
        }

        return $document;
    }
}
