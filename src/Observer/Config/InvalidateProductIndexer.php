<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Observer\Config;

use DavidBel\AiSearch\Indexer\Versioning;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class InvalidateProductIndexer implements ObserverInterface
{
    public function __construct(
        private readonly Versioning $versioning
    ) {
    }

    public function execute(Observer $observer): void
    {
        $this->versioning->invalidateProductIndexerWhenNeeded();
    }
}
