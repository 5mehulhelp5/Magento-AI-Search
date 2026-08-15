<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning\Target;

use DavidBel\AiSearch\Config\EmbedderConfig;
use DavidBel\AiSearch\Config\DataProcessingConfig;
use DavidBel\AiSearch\Config\SearchResultConfig;
use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Indexer\Versioning\ConfigurationFingerprint;
use DavidBel\AiSearch\Indexer\Versioning\IndexName;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex\QueryConfigurationSnapshot;
use DavidBel\AiSearch\Indexer\Versioning\State;
use DavidBel\AiSearch\Indexer\Versioning\State\Flag;
use DavidBel\AiSearch\Indexer\Versioning\Target;
use DavidBel\AiSearch\Indexer\Versioning\VersionLock;
use RuntimeException;

class Preparation
{
    public function __construct(
        private readonly ConfigurationFingerprint $configurationFingerprint,
        private readonly EmbedderConfig $embedderConfig,
        private readonly DataProcessingConfig $dataProcessingConfig,
        private readonly SearchResultConfig $searchResultConfig,
        private readonly IndexName $indexName,
        private readonly Flag $stateFlag,
        private readonly OpenSearch $openSearch,
        private readonly VersionLock $versionLock
    ) {
    }

    public function prepare(): void
    {
        $this->lock();

        try {
            $state = $this->stateFlag->get();
            $configurationFingerprint = $this->configurationFingerprint->get();
            $existingTarget = $state->target;

            if ($this->canResume($existingTarget, $configurationFingerprint)) {
                $this->resume($state, $existingTarget);

                return;
            }

            $targetIndex = $this->createNextPhysicalIndex($state, $configurationFingerprint);
            $this->stateFlag->save(new State(
                $state->active,
                new Target($targetIndex, false),
                $state->cacheStatus
            ));
        } finally {
            $this->versionLock->unlock();
        }
    }

    public function markDocumentProcessingComplete(): void
    {
        $this->lock();

        try {
            $state = $this->stateFlag->get();

            if ($state->target === null) {
                throw new RuntimeException('A target search index version has not been prepared.');
            }

            $this->stateFlag->save(new State(
                $state->active,
                new Target($state->target->physicalIndex, true),
                $state->cacheStatus
            ));
        } finally {
            $this->versionLock->unlock();
        }
    }

    private function canResume(
        ?Target $target,
        string $configurationFingerprint
    ): bool {
        return $target !== null
            && $target->physicalIndex->configurationFingerprint === $configurationFingerprint;
    }

    private function resume(State $state, ?Target $target): void
    {
        if ($target === null) {
            throw new RuntimeException('A target search index version is required for resuming.');
        }

        $this->openSearch->create($target->physicalIndex);
        $this->stateFlag->save(new State(
            $state->active,
            new Target($target->physicalIndex, false),
            $state->cacheStatus
        ));
    }

    private function createNextPhysicalIndex(
        State $state,
        string $configurationFingerprint
    ): PhysicalIndex {
        $versionNumber = max(
            $state->active->number ?? 0,
            $state->target->physicalIndex->number ?? 0
        ) + 1;
        $indexName = $this->indexName->getVersionName($versionNumber);

        while ($this->openSearch->exists($indexName)) {
            $versionNumber++;
            $indexName = $this->indexName->getVersionName($versionNumber);
        }

        $physicalIndex = new PhysicalIndex(
            $versionNumber,
            $indexName,
            $configurationFingerprint,
            new QueryConfigurationSnapshot(
                $this->embedderConfig->getEmbeddingModel(),
                $this->embedderConfig->getVectorDimensions(),
                $this->searchResultConfig->getEmbedderQueryTemplate()
            )
        );
        $this->openSearch->create($physicalIndex);

        return $physicalIndex;
    }

    private function lock(): void
    {
        if (!$this->versionLock->lock($this->dataProcessingConfig->getIndexerLockTimeoutSeconds())) {
            throw new RuntimeException('The search index version is currently being changed.');
        }
    }
}
