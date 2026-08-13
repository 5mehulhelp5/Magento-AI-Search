<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion;

use DavidBel\AiSearch\Config\DataProcessingConfig;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;

class ChunkProcessingCleanup
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly DateTime $dateTime,
        private readonly DataProcessingConfig $dataProcessingConfig
    ) {
    }

    public function execute(): int
    {
        $expiredBefore = $this->dateTime->gmtDate(
            null,
            sprintf(
                '-%d hours',
                $this->dataProcessingConfig->getCleanupResultRetentionHours()
            )
        );

        return $this->collectionFactory
            ->create()
            ->getResourceModel()
            ->deleteExhaustedUpsertsOrExpiredResults(
                $this->dataProcessingConfig->getCleanupAttemptThreshold(),
                $expiredBefore
            );
    }
}
