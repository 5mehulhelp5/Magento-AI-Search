<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Cron;

use DavidBel\AiSearch\Indexer\Versioning;
use DavidBel\AiSearch\Ingestion\ChunkDeletionFactory;

class ChunkDeletion
{
    public function __construct(
        private readonly ChunkDeletionFactory $chunkDeletionFactory,
        private readonly Versioning $versioning
    ) {
    }

    public function execute(): void
    {
        $indexVersion = $this->versioning->getOptionalIngestionIndexVersion();

        if ($indexVersion === null) {
            return;
        }

        $this->chunkDeletionFactory->create()->execute($indexVersion);
    }
}
