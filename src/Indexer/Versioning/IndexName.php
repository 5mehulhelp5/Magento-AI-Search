<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning;

use DavidBel\AiSearch\Config\IndexVersionConfig;
use Magento\Elasticsearch\Model\Config as SearchConfig;
use UnexpectedValueException;

class IndexName
{
    public function __construct(
        private readonly SearchConfig $searchConfig,
        private readonly IndexVersionConfig $indexVersionConfig
    ) {
    }

    public function getAlias(): string
    {
        $prefix = $this->searchConfig->getIndexPrefix();
        $prefix = trim($prefix);

        if ($prefix === '') {
            return $this->indexVersionConfig->getIndexName();
        }

        return $prefix . '_' . $this->indexVersionConfig->getIndexName();
    }

    public function getVersionName(int $versionNumber): string
    {
        if ($versionNumber < 1) {
            throw new UnexpectedValueException('The OpenSearch index version number must be positive.');
        }

        return sprintf('%s_v%d', $this->getAlias(), $versionNumber);
    }
}
