<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Cron;

use DavidBel\AiSearch\Workflow\ChunkProcessingCleanup as ChunkProcessingCleanupWorkflow;

class ChunkProcessingCleanup
{
    public function __construct(
        private readonly ChunkProcessingCleanupWorkflow $chunkProcessingCleanup
    ) {
    }

    public function execute(): void
    {
        $this->chunkProcessingCleanup->execute();
    }
}
