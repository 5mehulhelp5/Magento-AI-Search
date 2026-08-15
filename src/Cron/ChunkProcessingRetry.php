<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Cron;

use DavidBel\AiSearch\Indexer\Versioning;
use DavidBel\AiSearch\Ingestion\ChunkProcessingRetry as IngestionChunkProcessingRetry;

class ChunkProcessingRetry
{
    public function __construct(
        private readonly IngestionChunkProcessingRetry $chunkProcessingRetry,
        private readonly Versioning $versioning
    ) {
    }

    public function execute(): void
    {
        if (!$this->versioning->hasIngestionIndexVersion()) {
            return;
        }

        $this->chunkProcessingRetry->execute(
            $this->versioning->getIngestionIndexVersion()
        );
    }
}
