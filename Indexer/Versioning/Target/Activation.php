<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning\Target;

use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Indexer\Versioning\ConfigurationFingerprint;
use DavidBel\AiSearch\Indexer\Versioning\State;
use DavidBel\AiSearch\Indexer\Versioning\State\CacheStatus;
use DavidBel\AiSearch\Indexer\Versioning\State\Flag;
use DavidBel\AiSearch\Indexer\Versioning\Target\Activation\CacheClean;
use DavidBel\AiSearch\Indexer\Versioning\Target\Activation\Readiness;
use DavidBel\AiSearch\Indexer\Versioning\VersionLock;
use RuntimeException;

class Activation
{
    public function __construct(
        private readonly ConfigurationFingerprint $configurationFingerprint,
        private readonly Flag $stateFlag,
        private readonly OpenSearch $openSearch,
        private readonly Readiness $targetReadiness,
        private readonly CacheClean $cacheClean,
        private readonly VersionLock $versionLock
    ) {
    }

    public function execute(): bool
    {
        if (!$this->versionLock->lock(0)) {
            return false;
        }

        try {
            $state = $this->stateFlag->get();

            if ($state->cacheStatus === CacheStatus::Required) {
                $this->completeCacheClean($state);
                $state = $this->stateFlag->get();
            }

            if (!$this->canActivate($state)) {
                return false;
            }

            $this->activate($state);

            return true;
        } finally {
            $this->versionLock->unlock();
        }
    }

    private function canActivate(State $state): bool
    {
        $target = $state->target;

        if ($target === null || !$target->documentProcessingCompleted) {
            return false;
        }

        if ($target->physicalIndex->configurationFingerprint !== $this->configurationFingerprint->get()) {
            return false;
        }

        if (!$this->openSearch->indexExists($target->physicalIndex->indexName)) {
            return false;
        }

        return $this->targetReadiness->isReady($target->physicalIndex->number);
    }

    private function activate(State $state): void
    {
        $target = $state->target;

        if ($target === null) {
            throw new RuntimeException('A target search index version is required for activation.');
        }

        $this->openSearch->activateIndex($target->physicalIndex);
        $activatedState = new State($target->physicalIndex, null, CacheStatus::Required);
        $this->stateFlag->save($activatedState);
        $this->completeCacheClean($activatedState);
    }

    private function completeCacheClean(State $state): void
    {
        $this->cacheClean->execute();
        $this->stateFlag->save(new State(
            $state->active,
            $state->target,
            CacheStatus::Clean
        ));
    }
}
