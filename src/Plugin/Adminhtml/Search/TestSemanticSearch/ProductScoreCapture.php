<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Plugin\Adminhtml\Search\TestSemanticSearch;

use DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch\ResultProvider\SearchScores;
use DavidBel\AiSearch\Search\Candidates;
use DavidBel\AiSearch\Search\VectorSearch;

class ProductScoreCapture
{
    public function __construct(
        private readonly SearchScores $searchScores
    ) {
    }

    public function afterExecute(VectorSearch $subject, Candidates $candidates): Candidates
    {
        $this->searchScores->scoresByProductId = $candidates->scoresByProductId;

        return $candidates;
    }
}
