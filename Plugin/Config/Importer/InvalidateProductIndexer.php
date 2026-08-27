<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Plugin\Config\Importer;

use DavidBel\AiSearch\Indexer\Versioning;
use Magento\Config\Model\Config\Importer;

class InvalidateProductIndexer
{
    public function __construct(
        private readonly Versioning $versioning
    ) {
    }

    /**
     * @param list<string> $result
     * @return list<string>
     */
    public function afterImport(Importer $subject, array $result): array
    {
        $this->versioning->invalidateProductIndexerWhenNeeded();

        return $result;
    }
}
