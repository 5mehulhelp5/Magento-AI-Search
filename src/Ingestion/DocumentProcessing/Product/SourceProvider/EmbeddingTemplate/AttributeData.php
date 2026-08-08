<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\EmbeddingTemplate;

readonly class AttributeData
{
    /**
     * @param array<int, array{code: string, backend_type: string, frontend_input: string}> $attributesById
     * @param array<string, array<string, string>> $rawValues
     * @param array<int, array<int, string>> $optionLabels
     */
    public function __construct(
        public array $attributesById,
        public array $rawValues,
        public array $optionLabels
    ) {
    }
}
