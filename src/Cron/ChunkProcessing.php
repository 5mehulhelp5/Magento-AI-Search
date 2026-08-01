<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Cron;

use DavidBel\AiSearch\Workflow\ChunkProcessingFactory;

class ChunkProcessing
{
    public function __construct(
        private readonly ChunkProcessingFactory $chunkProcessingFactory
    ) {
    }

    public function execute(): void
    {
        $this->chunkProcessingFactory->create()->execute();
    }
}
