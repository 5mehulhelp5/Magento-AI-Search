<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml\Dashboard;

use DavidBel\AiSearch\Model\ResourceModel\ProductIndexer\Progress;
use Magento\Backend\Block\Template;

class Indexing extends Template
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly Progress $progress,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getQueuedProductCount(): int
    {
        return $this->progress->getQueuedProductCount();
    }
}
