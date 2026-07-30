<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Repository\EmbeddingBacklog;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklogFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;

readonly class Get
{
    public function __construct(
        private EmbeddingBacklogFactory $embeddingBacklogFactory,
        private CollectionFactory $collectionFactory
    ) {
    }

    public function execute(int $backlogId): EmbeddingBacklogInterface
    {
        $embeddingBacklog = $this->embeddingBacklogFactory->create();
        $this->collectionFactory->create()
            ->getResourceModel()
            ->load($embeddingBacklog, $backlogId);

        if ($embeddingBacklog->getBacklogId() === null) {
            throw NoSuchEntityException::singleField(
                EmbeddingBacklogInterface::BACKLOG_ID,
                $backlogId
            );
        }

        return $embeddingBacklog;
    }
}
