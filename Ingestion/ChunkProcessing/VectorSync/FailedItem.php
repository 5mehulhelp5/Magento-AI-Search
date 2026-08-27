<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync;

use DavidBel\AiSearch\Model\EmbeddingBacklog\ErrorDetails;

readonly class FailedItem
{
    public function __construct(
        public Item $item,
        public ErrorDetails $errorDetails
    ) {
    }
}
