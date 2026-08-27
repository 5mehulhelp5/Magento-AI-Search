<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning;

use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex\QueryConfigurationSnapshot;
use InvalidArgumentException;

readonly class PhysicalIndex
{
    public function __construct(
        public int $number,
        public string $indexName,
        public string $configurationFingerprint,
        public QueryConfigurationSnapshot $queryConfigurationSnapshot
    ) {
        if ($this->number < 1 || $this->indexName === '' || $this->configurationFingerprint === '') {
            throw new InvalidArgumentException('The search index version is invalid.');
        }
    }

    /**
     * @return array<string, array<string, int|string>|int|string>
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'index_name' => $this->indexName,
            'configuration_fingerprint' => $this->configurationFingerprint,
            'query_configuration' => $this->queryConfigurationSnapshot->toArray(),
        ];
    }
}
