<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Repository\EmbeddingBacklog;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use Exception;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Model\AbstractModel;

readonly class Save
{
    public function __construct(
        private EmbeddingBacklogResource $embeddingBacklogResource
    ) {
    }

    public function execute(EmbeddingBacklogInterface $embeddingBacklog): EmbeddingBacklogInterface
    {
        if (!$embeddingBacklog instanceof AbstractModel) {
            throw new CouldNotSaveException(
                __('The embedding backlog implementation cannot be persisted.')
            );
        }

        try {
            $this->embeddingBacklogResource->save($embeddingBacklog);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the AI search embedding backlog entry.'),
                $exception
            );
        }

        return $embeddingBacklog;
    }
}
