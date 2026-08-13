<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use UnexpectedValueException;

class SearchConfig
{
    private const string INDEX_NAME = 'davidbel_ai_search_chunks';
    private const int INDEX_SCHEMA_VERSION = 1;
    private const string VECTOR_METHOD = 'hnsw';
    private const string XML_PATH_VECTOR_ENGINE =
        'davidbel_ai_search_search_source/search_engine/vector_engine';
    private const string XML_PATH_VECTOR_SPACE =
        'davidbel_ai_search_search_source/search_engine/vector_space';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getIndexName(): string
    {
        return self::INDEX_NAME;
    }

    public function getIndexSchemaVersion(): int
    {
        return self::INDEX_SCHEMA_VERSION;
    }

    public function getVectorMethod(): string
    {
        return self::VECTOR_METHOD;
    }

    public function getVectorEngine(): string
    {
        return $this->getStringValue(self::XML_PATH_VECTOR_ENGINE);
    }

    public function getVectorSpace(): string
    {
        return $this->getStringValue(self::XML_PATH_VECTOR_SPACE);
    }

    private function getStringValue(string $path): string
    {
        $value = $this->scopeConfig->getValue($path);

        if (!is_string($value)) {
            throw new UnexpectedValueException(
                sprintf('Configuration path "%s" must contain a string.', $path)
            );
        }

        return $value;
    }
}
