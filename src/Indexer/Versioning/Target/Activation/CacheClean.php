<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning\Target\Activation;

use Magento\Framework\App\Cache\TypeListInterface;

class CacheClean
{
    private const string FULL_PAGE_CACHE = 'full_page';

    public function __construct(
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    public function execute(): void
    {
        $this->cacheTypeList->cleanType(self::FULL_PAGE_CACHE);
    }
}
