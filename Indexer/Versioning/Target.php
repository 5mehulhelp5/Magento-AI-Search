<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning;

readonly class Target
{
    public function __construct(
        public PhysicalIndex $physicalIndex,
        public bool $documentProcessingCompleted
    ) {
    }

    /**
     * @return array<string, array<string, mixed>|bool>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->physicalIndex->toArray(),
            'document_processing_completed' => $this->documentProcessingCompleted,
        ];
    }
}
