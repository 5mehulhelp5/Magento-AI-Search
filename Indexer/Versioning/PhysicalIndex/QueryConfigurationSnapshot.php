<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex;

use InvalidArgumentException;

readonly class QueryConfigurationSnapshot
{
    public function __construct(
        public string $embeddingModel,
        public int $vectorDimensions,
        public string $queryTemplate
    ) {
        if ($this->embeddingModel === '' || $this->vectorDimensions < 1 || $this->queryTemplate === '') {
            throw new InvalidArgumentException('The query configuration snapshot is invalid.');
        }
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'embedding_model' => $this->embeddingModel,
            'vector_dimensions' => $this->vectorDimensions,
            'query_template' => $this->queryTemplate,
        ];
    }
}
