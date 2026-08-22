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
use DavidBel\AiSearch\Log\ProcessingLogger;
use Throwable;

class ChunkProcessingRetry
{
    public function __construct(
        private readonly IngestionChunkProcessingRetry $chunkProcessingRetry,
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

            $this->chunkProcessingRetry->execute(
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
