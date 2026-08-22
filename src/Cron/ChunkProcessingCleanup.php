<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Cron;

use DavidBel\AiSearch\Indexer\Versioning;
use DavidBel\AiSearch\Ingestion\ChunkProcessingCleanup as IngestionChunkProcessingCleanup;
use DavidBel\AiSearch\Log\ProcessingLogger;
use Throwable;

class ChunkProcessingCleanup
{
    public function __construct(
        private readonly IngestionChunkProcessingCleanup $chunkProcessingCleanup,
        private readonly Versioning $versioning,
        private readonly ProcessingLogger $processingLogger
    ) {
    }

    public function execute(): void
    {
        $this->processingLogger->cronStarted(self::class);

        try {
            $ingestionIndexVersion = null;

            if ($this->versioning->hasIngestionIndexVersion()) {
                $ingestionIndexVersion = $this->versioning->getIngestionIndexVersion();
            }

            $targetIndexVersion = null;

            if ($this->versioning->hasTargetIndexVersion()) {
                $targetIndexVersion = $this->versioning->getTargetIndexVersion();
            }

            $this->chunkProcessingCleanup->execute(
                $ingestionIndexVersion,
                $targetIndexVersion
            );
            $this->versioning->deleteObsoletePhysicalIndexes();
        } catch (Throwable $throwable) {
            $this->processingLogger->cronFailed(self::class, $throwable);
            throw $throwable;
        } finally {
            $this->processingLogger->cronFinished(self::class);
        }
    }
}
