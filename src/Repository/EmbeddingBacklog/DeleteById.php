<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Repository\EmbeddingBacklog;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use Exception;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Model\AbstractModel;

readonly class DeleteById
{
    public function __construct(
        private Get $get,
        private EmbeddingBacklogResource $embeddingBacklogResource
    ) {
    }

    public function execute(int $backlogId): bool
    {
        $embeddingBacklog = $this->get->execute($backlogId);

        if (!$embeddingBacklog instanceof AbstractModel) {
            throw new CouldNotDeleteException(
                __('The embedding backlog implementation cannot be deleted.')
            );
        }

        try {
            $this->embeddingBacklogResource->delete($embeddingBacklog);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the AI search embedding backlog entry.'),
                $exception
            );
        }

        return true;
    }
}
