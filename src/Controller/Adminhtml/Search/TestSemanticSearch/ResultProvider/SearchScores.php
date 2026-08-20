<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch\ResultProvider;

class SearchScores
{
    /**
     * @var array<int, float>
     */
    public array $scoresByProductId = [];

    /**
     * @var array<int, float>
     */
    public array $scoresByChunkId = [];
}
