<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\Eligibility;

readonly class EligibleScope
{
    /**
     * @param list<int> $sourceProductIds
     */
    public function __construct(
        public int $storeId,
        public array $sourceProductIds
    ) {
    }
}
