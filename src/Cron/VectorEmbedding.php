<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Cron;

use DavidBel\AiSearch\Workflow\VectorEmbedding as VectorEmbeddingWorkflow;

class VectorEmbedding
{
    public function __construct(
        private readonly VectorEmbeddingWorkflow $vectorEmbedding
    ) {
    }

    public function execute(): void
    {
        $this->vectorEmbedding->execute();
    }
}
