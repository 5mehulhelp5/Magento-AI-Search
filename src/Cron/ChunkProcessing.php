<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Cron;

use DavidBel\AiSearch\Indexer\Versioning;
use DavidBel\AiSearch\Ingestion\ChunkProcessingFactory;

class ChunkProcessing
{
    public function __construct(
        private readonly ChunkProcessingFactory $chunkProcessingFactory,
        private readonly Versioning $versioning
    ) {
    }

    public function execute(): void
    {
        $indexVersion = $this->versioning->getOptionalIngestionIndexVersion();

        if ($indexVersion !== null) {
            $this->chunkProcessingFactory->create()->execute($indexVersion);
        }

        $this->versioning->activateTargetWhenReady();
    }
}
