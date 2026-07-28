<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Repository\Document;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Model\DocumentFactory;
use DavidBel\AiSearch\Model\ResourceModel\Document as DocumentResource;
use Magento\Framework\Exception\NoSuchEntityException;

readonly class Get
{
    public function __construct(
        private DocumentFactory $documentFactory,
        private DocumentResource $documentResource
    ) {
    }

    public function execute(int $documentId): DocumentInterface
    {
        $document = $this->documentFactory->create();
        $this->documentResource->load($document, $documentId);

        if ($document->getDocumentId() === null) {
            throw NoSuchEntityException::singleField(DocumentInterface::DOCUMENT_ID, $documentId);
        }

        return $document;
    }
}
