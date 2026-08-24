<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion;

use DavidBel\AiSearch\Config\SemanticDataProcessingConfig;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Maintenance
    as BacklogMaintenance;

class ChunkProcessingRetry
{
    public function __construct(
        private readonly BacklogMaintenance $backlogMaintenance,
        private readonly SemanticDataProcessingConfig $semanticDataProcessingConfig
    ) {
    }

    public function execute(int $indexVersion): int
    {
        return $this->backlogMaintenance->markFailedAsPending(
            $indexVersion,
            $this->semanticDataProcessingConfig->getRetryAttemptThreshold()
        );
    }
}
