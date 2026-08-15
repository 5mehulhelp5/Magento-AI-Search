<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion;

use DavidBel\AiSearch\Config\DataProcessingConfig;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Maintenance
    as BacklogMaintenance;

class ChunkProcessingRetry
{
    public function __construct(
        private readonly BacklogMaintenance $backlogMaintenance,
        private readonly DataProcessingConfig $dataProcessingConfig
    ) {
    }

    public function execute(int $indexVersion): int
    {
        return $this->backlogMaintenance->markFailedAsPending(
            $indexVersion,
            $this->dataProcessingConfig->getRetryAttemptThreshold()
        );
    }
}
