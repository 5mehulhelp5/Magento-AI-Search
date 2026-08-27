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
use DavidBel\AiSearch\Log\Logger;
use Throwable;

class ChunkDelete
{
    public function __construct(
        private readonly ChunkDeleteFactory $chunkDeleteFactory,
        private readonly Versioning $versioning,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        $this->logger->cronStarted(self::class);

        try {
            if (!$this->versioning->hasIngestionIndexVersion()) {
                return;
            }

            $this->chunkDeleteFactory->create()->execute(
                $this->versioning->getIngestionIndexVersion()
            );
        } catch (Throwable $throwable) {
            $this->logger->cronFailed(self::class, $throwable);
            throw $throwable;
        } finally {
            $this->logger->cronFinished(self::class);
        }
    }
}
