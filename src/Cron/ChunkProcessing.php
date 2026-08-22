<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Cron;

use DavidBel\AiSearch\Indexer\Versioning;
use DavidBel\AiSearch\Ingestion\ChunkProcessingFactory;
use DavidBel\AiSearch\Log\ProcessingLogger;
use Throwable;

class ChunkProcessing
{
    public function __construct(
        private readonly ChunkProcessingFactory $chunkProcessingFactory,
        private readonly Versioning $versioning,
        private readonly ProcessingLogger $processingLogger
    ) {
    }

    public function execute(): void
    {
        $this->processingLogger->cronStarted(self::class);

        try {
            if ($this->versioning->hasIngestionIndexVersion()) {
                $this->chunkProcessingFactory->create()->execute(
                    $this->versioning->getIngestionIndexVersion()
                );
            }

            $this->versioning->activateTargetWhenReady();
        } catch (Throwable $throwable) {
            $this->processingLogger->cronFailed(self::class, $throwable);
            throw $throwable;
        } finally {
            $this->processingLogger->cronFinished(self::class);
        }
    }
}
