<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Cron;

use DavidBel\AiSearch\Indexer\Versioning;
use DavidBel\AiSearch\Ingestion\ChunkProcessingCleanup as IngestionChunkProcessingCleanup;

class ChunkProcessingCleanup
{
    public function __construct(
        private readonly IngestionChunkProcessingCleanup $chunkProcessingCleanup,
        private readonly Versioning $versioning
    ) {
    }

    public function execute(): void
    {
        $this->chunkProcessingCleanup->execute(
            $this->versioning->getOptionalIngestionIndexVersion(),
            $this->versioning->getOptionalTargetIndexVersion()
        );
        $this->versioning->cleanupPhysicalIndexes();
    }
}
