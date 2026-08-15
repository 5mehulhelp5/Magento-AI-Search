<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Cron;

use DavidBel\AiSearch\Indexer\Versioning;
use DavidBel\AiSearch\Ingestion\ChunkDeleteFactory;

class ChunkDelete
{
    public function __construct(
        private readonly ChunkDeleteFactory $chunkDeleteFactory,
        private readonly Versioning $versioning
    ) {
    }

    public function execute(): void
    {
        if (!$this->versioning->hasIngestionIndexVersion()) {
            return;
        }

        $this->chunkDeleteFactory->create()->execute(
            $this->versioning->getIngestionIndexVersion()
        );
    }
}
