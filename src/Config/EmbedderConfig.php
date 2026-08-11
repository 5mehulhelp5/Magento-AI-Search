<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use UnexpectedValueException;

class EmbedderConfig
{
    private const string XML_PATH_BASE_URL =
        'davidbel_ai_search_search_source/ai_server/base_url';
    private const string XML_PATH_EMBEDDING_MODEL =
        'davidbel_ai_search_search_source/ai_server/embedding_model';
    private const string XML_PATH_VECTOR_DIMENSIONS =
        'davidbel_ai_search_search_source/ai_server/vector_dimensions';
    private const string XML_PATH_REQUEST_TIMEOUT_SECONDS =
        'davidbel_ai_search_search_source/ai_server/request_timeout_seconds';
    private const string XML_PATH_EMBEDDER_DOCUMENT_TEMPLATE =
        'davidbel_ai_search_search_source/ai_server/embedder_document_template';
    private const string XML_PATH_MAXIMUM_CHUNK_TOKENS =
        'davidbel_ai_search_search_source/ai_server/maximum_chunk_tokens';
    private const string XML_PATH_CHUNK_OVERLAP_TOKENS =
        'davidbel_ai_search_search_source/ai_server/chunk_overlap_tokens';
    private const string XML_PATH_ESTIMATED_CHARACTERS_PER_TOKEN =
        'davidbel_ai_search_search_source/ai_server/estimated_characters_per_token';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getBaseUrl(): string
    {
        return $this->getStringValue(self::XML_PATH_BASE_URL);
    }

    public function getEmbeddingModel(): string
    {
        return $this->getStringValue(self::XML_PATH_EMBEDDING_MODEL);
    }

    public function getVectorDimensions(): int
    {
        return $this->getPositiveInteger(self::XML_PATH_VECTOR_DIMENSIONS);
    }

    public function getRequestTimeoutSeconds(): int
    {
        return $this->getPositiveInteger(self::XML_PATH_REQUEST_TIMEOUT_SECONDS);
    }

    public function getEmbedderDocumentTemplate(): string
    {
        return $this->getStringValue(self::XML_PATH_EMBEDDER_DOCUMENT_TEMPLATE);
    }

    public function getMaximumChunkTokens(): int
    {
        return $this->getPositiveInteger(self::XML_PATH_MAXIMUM_CHUNK_TOKENS);
    }

    public function getChunkOverlapTokens(): int
    {
        return $this->getNonNegativeInteger(self::XML_PATH_CHUNK_OVERLAP_TOKENS);
    }

    public function getEstimatedCharactersPerToken(): int
    {
        return $this->getPositiveInteger(self::XML_PATH_ESTIMATED_CHARACTERS_PER_TOKEN);
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

    private function getPositiveInteger(string $path): int
    {
        $value = $this->getInteger($path);

        if ($value < 1) {
            throw new UnexpectedValueException(
                sprintf('Configuration path "%s" must contain a positive integer.', $path)
            );
        }

        return $value;
    }

    private function getNonNegativeInteger(string $path): int
    {
        $value = $this->getInteger($path);

        if ($value < 0) {
            throw new UnexpectedValueException(
                sprintf('Configuration path "%s" must contain a non-negative integer.', $path)
            );
        }

        return $value;
    }

    private function getInteger(string $path): int
    {
        $value = filter_var($this->scopeConfig->getValue($path), FILTER_VALIDATE_INT);

        if (!is_int($value)) {
            throw new UnexpectedValueException(
                sprintf('Configuration path "%s" must contain an integer.', $path)
            );
        }

        return $value;
    }
}
