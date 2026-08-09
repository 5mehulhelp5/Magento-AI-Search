<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Config;

class IndexVersionConfig
{
    private const string INDEX_NAME = 'davidbel_ai_search_chunks';
    private const int INDEX_SCHEMA_VERSION = 1;
    private const string VECTOR_METHOD = 'hnsw';
    private const string VECTOR_ENGINE = 'faiss';
    private const string VECTOR_SPACE = 'l2';
    private const int LOCK_TIMEOUT_SECONDS = 10;

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
        return self::VECTOR_ENGINE;
    }

    public function getVectorSpace(): string
    {
        return self::VECTOR_SPACE;
    }

    public function getLockTimeoutSeconds(): int
    {
        return self::LOCK_TIMEOUT_SECONDS;
    }
}
