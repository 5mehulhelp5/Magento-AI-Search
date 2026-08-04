<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Cron;

use DavidBel\AiSearch\Workflow\ChunkDeletionFactory;

class ChunkDeletion
{
    public function __construct(
        private readonly ChunkDeletionFactory $chunkDeletionFactory
    ) {
    }

    public function execute(): void
    {
        $this->chunkDeletionFactory->create()->execute();
    }
}
