<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning;

use DavidBel\AiSearch\Config\SearchConfig;
use Magento\Elasticsearch\Model\Config as MagentoSearchConfig;
use UnexpectedValueException;

class IndexName
{
    public function __construct(
        private readonly MagentoSearchConfig $magentoSearchConfig,
        private readonly SearchConfig $searchConfig
    ) {
    }

    public function getAlias(): string
    {
        $prefix = $this->magentoSearchConfig->getIndexPrefix();
        $prefix = trim($prefix);

        if ($prefix === '') {
            return $this->searchConfig->getIndexName();
        }

        return $prefix . '_' . $this->searchConfig->getIndexName();
    }

    public function getVersionName(int $versionNumber): string
    {
        if ($versionNumber < 1) {
            throw new UnexpectedValueException('The OpenSearch index version number must be positive.');
        }

        return sprintf('%s_v%d', $this->getAlias(), $versionNumber);
    }
}
