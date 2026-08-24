<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion;

use DavidBel\AiSearch\Config\SemanticDataProcessingConfig;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\IndexVersion
    as BacklogIndexVersion;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Maintenance
    as BacklogMaintenance;
use Magento\Framework\Stdlib\DateTime\DateTime;

class ChunkProcessingCleanup
{
    public function __construct(
        private readonly BacklogIndexVersion $backlogIndexVersion,
        private readonly BacklogMaintenance $backlogMaintenance,
        private readonly DateTime $dateTime,
        private readonly SemanticDataProcessingConfig $semanticDataProcessingConfig
    ) {
    }

    public function execute(
        ?int $ingestionIndexVersion,
        ?int $targetIndexVersion
    ): int {
        $expiredBefore = $this->dateTime->gmtDate(
            null,
            sprintf(
                '-%d hours',
                $this->semanticDataProcessingConfig->getCleanupResultRetentionHours()
            )
        );

        $deletedCount = 0;

        if ($ingestionIndexVersion !== null) {
            $deletedCount += $this->backlogIndexVersion->deleteItemsOutsideIndexVersion(
                $ingestionIndexVersion
            );
        }

        return $deletedCount + $this->backlogMaintenance->deleteExhaustedUpsertsOrExpiredResults(
            $this->semanticDataProcessingConfig->getCleanupAttemptThreshold(),
            $expiredBefore,
            $targetIndexVersion
        );
    }
}
