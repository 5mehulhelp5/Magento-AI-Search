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
use DavidBel\AiSearch\Log\ProcessingLogger;
use Throwable;

class ChunkDelete
{
    public function __construct(
        private readonly ChunkDeleteFactory $chunkDeleteFactory,
        private readonly Versioning $versioning,
        private readonly ProcessingLogger $processingLogger
    ) {
    }

    public function execute(): void
    {
        $this->processingLogger->cronStarted(self::class);

        try {
            if (!$this->versioning->hasIngestionIndexVersion()) {
                return;
            }

            $this->chunkDeleteFactory->create()->execute(
                $this->versioning->getIngestionIndexVersion()
            );
        } catch (Throwable $throwable) {
            $this->processingLogger->cronFailed(self::class, $throwable);
            throw $throwable;
        } finally {
            $this->processingLogger->cronFinished(self::class);
        }
    }
}
