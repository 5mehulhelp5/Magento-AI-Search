<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Config;

class IngestionConfig
{
    private const int DOCUMENT_PROCESSING_BATCH_SIZE = 200;
    private const int EMBEDDING_BATCH_SIZE = 100;
    private const int CONCURRENT_EMBEDDING_REQUESTS = 3;
    private const int EMBEDDING_MAXIMUM_RUNTIME_SECONDS = 600;
    private const int DELETION_BATCH_SIZE = 1_000;
    private const int DELETION_UPSERT_ATTEMPT_THRESHOLD = 3;
    private const int DELETION_MAXIMUM_RUNTIME_SECONDS = 600;
    private const int RETRY_ATTEMPT_THRESHOLD = 3;
    private const int CLEANUP_ATTEMPT_THRESHOLD = 3;
    private const string CLEANUP_RESULT_RETENTION = '-24 hours';

    public function getDocumentProcessingBatchSize(): int
    {
        return self::DOCUMENT_PROCESSING_BATCH_SIZE;
    }

    public function getEmbeddingBatchSize(): int
    {
        return self::EMBEDDING_BATCH_SIZE;
    }

    public function getConcurrentEmbeddingRequests(): int
    {
        return self::CONCURRENT_EMBEDDING_REQUESTS;
    }

    public function getEmbeddingMaximumRuntimeSeconds(): int
    {
        return self::EMBEDDING_MAXIMUM_RUNTIME_SECONDS;
    }

    public function getDeletionBatchSize(): int
    {
        return self::DELETION_BATCH_SIZE;
    }

    public function getDeletionUpsertAttemptThreshold(): int
    {
        return self::DELETION_UPSERT_ATTEMPT_THRESHOLD;
    }

    public function getDeletionMaximumRuntimeSeconds(): int
    {
        return self::DELETION_MAXIMUM_RUNTIME_SECONDS;
    }

    public function getRetryAttemptThreshold(): int
    {
        return self::RETRY_ATTEMPT_THRESHOLD;
    }

    public function getCleanupAttemptThreshold(): int
    {
        return self::CLEANUP_ATTEMPT_THRESHOLD;
    }

    public function getCleanupResultRetention(): string
    {
        return self::CLEANUP_RESULT_RETENTION;
    }
}
