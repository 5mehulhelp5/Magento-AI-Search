<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning\Target;

use DavidBel\AiSearch\Indexer\Versioning\ConfigurationFingerprint;
use DavidBel\AiSearch\Indexer\Versioning\OpenSearchIndex;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex;
use DavidBel\AiSearch\Indexer\Versioning\State;
use DavidBel\AiSearch\Indexer\Versioning\State\CacheStatus;
use DavidBel\AiSearch\Indexer\Versioning\State\Flag;
use DavidBel\AiSearch\Indexer\Versioning\Target\Activation\CacheClean;
use DavidBel\AiSearch\Indexer\Versioning\Target\Activation\Readiness;
use DavidBel\AiSearch\Indexer\Versioning\VersionLock;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class Activation
{
    public function __construct(
        private readonly ConfigurationFingerprint $configurationFingerprint,
        private readonly Flag $stateFlag,
        private readonly OpenSearchIndex $openSearchIndex,
        private readonly Readiness $targetReadiness,
        private readonly CacheClean $cacheClean,
        private readonly VersionLock $versionLock,
        private readonly LoggerInterface $logger
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

        if (!$this->openSearchIndex->exists($target->physicalIndex->indexName)) {
            return false;
        }

        return $this->targetReadiness->isReady();
    }

    private function activate(State $state): void
    {
        $target = $state->target;

        if ($target === null) {
            throw new RuntimeException('A target search index version is required for activation.');
        }

        $this->openSearchIndex->activate($target->physicalIndex);
        $activatedState = new State($target->physicalIndex, null, CacheStatus::Required);
        $this->stateFlag->save($activatedState);
        $this->deletePreviousActiveIndex($state->active, $target->physicalIndex);
        $this->completeCacheClean($activatedState);
    }

    private function deletePreviousActiveIndex(
        ?PhysicalIndex $active,
        PhysicalIndex $target
    ): void {
        if ($active === null || $active->indexName === $target->indexName) {
            return;
        }

        try {
            $this->openSearchIndex->delete($active->indexName);
        } catch (Throwable $throwable) {
            $this->logger->warning(
                'The previous active search index version could not be deleted.',
                ['exception' => $throwable]
            );
        }
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
