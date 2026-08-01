<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Cron;

use DavidBel\AiSearch\Workflow\ChunkProcessingRetry as ChunkProcessingRetryWorkflow;

class ChunkProcessingRetry
{
    public function __construct(
        private readonly ChunkProcessingRetryWorkflow $chunkProcessingRetry
    ) {
    }

    public function execute(): void
    {
        $this->chunkProcessingRetry->execute();
    }
}
