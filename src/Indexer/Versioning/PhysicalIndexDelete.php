<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning;

use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Indexer\Versioning\State\Flag;
use Psr\Log\LoggerInterface;
use Throwable;

class PhysicalIndexDelete
{
    public function __construct(
        private readonly Flag $stateFlag,
        private readonly OpenSearch $openSearch,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): int
    {
        $activeIndexName = $this->getActiveIndexName();

        if ($activeIndexName === null) {
            return 0;
        }

        $deletedCount = 0;

        foreach ($this->getVersionIndexNames() as $indexName) {
            $deletedCount += $this->deleteIfObsolete($indexName, $activeIndexName);
        }

        return $deletedCount;
    }

    private function getActiveIndexName(): ?string
    {
        $state = $this->stateFlag->get();

        if ($state->active === null || $state->target !== null) {
            return null;
        }

        return $state->active->indexName;
    }

    /**
     * @return list<string>
     */
    private function getVersionIndexNames(): array
    {
        try {
            return $this->openSearch->getVersionIndexNames();
        } catch (Throwable $throwable) {
            $this->logger->warning(
                'Obsolete OpenSearch index versions could not be listed.',
                ['exception' => $throwable]
            );

            return [];
        }
    }

    private function deleteIfObsolete(string $indexName, string $activeIndexName): int
    {
        if ($indexName === $activeIndexName) {
            return 0;
        }

        try {
            $this->openSearch->deleteIndex($indexName);
        } catch (Throwable $throwable) {
            $this->logger->warning(
                'An obsolete OpenSearch index version could not be deleted.',
                [
                    'index_name' => $indexName,
                    'exception' => $throwable,
                ]
            );

            return 0;
        }

        return 1;
    }
}
