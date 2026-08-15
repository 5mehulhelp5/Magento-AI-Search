<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion;

use DavidBel\AiSearch\Config\DataProcessingConfig;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\IndexVersion
    as BacklogIndexVersion;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Maintenance
    as BacklogMaintenance;

class ChunkProcessingRetry
{
    public function __construct(
        private readonly BacklogIndexVersion $backlogIndexVersion,
        private readonly BacklogMaintenance $backlogMaintenance,
        private readonly DataProcessingConfig $dataProcessingConfig
    ) {
    }

    public function execute(): int
    {
        $indexVersion = $this->backlogIndexVersion->getHighestIndexVersion();

        if ($indexVersion === null) {
            return 0;
        }

        return $this->backlogMaintenance->markFailedAsPending(
            $indexVersion,
            $this->dataProcessingConfig->getRetryAttemptThreshold()
        );
    }
}
