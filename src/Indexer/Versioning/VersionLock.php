<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning;

use Magento\Framework\Lock\LockManagerInterface;

class VersionLock
{
    private const string LOCK_NAME = 'davidbel_ai_search_index_version';

    public function __construct(
        private readonly LockManagerInterface $lockManager
    ) {
    }

    public function lock(int $timeoutSeconds): bool
    {
        return $this->lockManager->lock(self::LOCK_NAME, $timeoutSeconds);
    }

    public function unlock(): void
    {
        $this->lockManager->unlock(self::LOCK_NAME);
    }
}
