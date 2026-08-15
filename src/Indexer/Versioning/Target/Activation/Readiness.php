<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning\Target\Activation;

use DavidBel\AiSearch\Config\DataProcessingConfig;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\IndexVersion
    as BacklogIndexVersion;

class Readiness
{
    public function __construct(
        private readonly BacklogIndexVersion $backlogIndexVersion,
        private readonly DataProcessingConfig $dataProcessingConfig
    ) {
    }

    public function isReady(int $indexVersion): bool
    {
        $progress = $this->backlogIndexVersion->getFullReindexProgress($indexVersion);

        if ($progress['unfinished'] > 0) {
            return false;
        }

        if ($progress['total'] === 0) {
            return true;
        }

        return $progress['indexed'] * 100 >= $progress['total']
            * $this->dataProcessingConfig->getIndexerMinimumSuccessPercentage();
    }
}
