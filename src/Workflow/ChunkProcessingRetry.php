<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;

class ChunkProcessingRetry
{
    private const int ATTEMPT_THRESHOLD = 3;

    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function execute(): int
    {
        return $this->collectionFactory
            ->create()
            ->getResourceModel()
            ->markFailedAsPending(self::ATTEMPT_THRESHOLD);
    }
}
