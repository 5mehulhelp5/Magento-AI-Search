<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Config;

class EmbedderConfig
{
    private const string BASE_URL = 'http://host.docker.internal:1234';
    private const string MODEL = 'text-embedding-embeddinggemma-300m-qat';
    private const int VECTOR_DIMENSIONS = 768;
    private const int REQUEST_TIMEOUT_SECONDS = 60;
    private const string DOCUMENT_TEMPLATE = 'title: {title} | text: {text}';
    private const string QUERY_TEMPLATE = 'task: search result | query: {text}';

    public function getBaseUrl(): string
    {
        return self::BASE_URL;
    }

    public function getModel(): string
    {
        return self::MODEL;
    }

    public function getVectorDimensions(): int
    {
        return self::VECTOR_DIMENSIONS;
    }

    public function getRequestTimeoutSeconds(): int
    {
        return self::REQUEST_TIMEOUT_SECONDS;
    }

    public function getDocumentTemplate(): string
    {
        return self::DOCUMENT_TEMPLATE;
    }

    public function getQueryTemplate(): string
    {
        return self::QUERY_TEMPLATE;
    }
}
