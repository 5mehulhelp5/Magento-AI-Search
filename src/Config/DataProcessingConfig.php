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

class DataProcessingConfig
{
    private const string XML_PATH_DOCUMENT_PROCESSING_BATCH_SIZE =
        'davidbel_ai_search_data_processing/document_processing/batch_size';
    private const string XML_PATH_VECTOR_EMBEDDING_BATCH_SIZE =
        'davidbel_ai_search_data_processing/vector_embedding/batch_size';
    private const string XML_PATH_CONCURRENT_EMBEDDING_REQUESTS =
        'davidbel_ai_search_data_processing/vector_embedding/concurrent_requests';
    private const string XML_PATH_EMBEDDING_MAXIMUM_RUNTIME_SECONDS =
        'davidbel_ai_search_data_processing/vector_embedding/maximum_runtime_seconds';
    private const string XML_PATH_VECTOR_DELETION_BATCH_SIZE =
        'davidbel_ai_search_data_processing/vector_deletion/batch_size';
    private const string XML_PATH_DELETION_UPSERT_ATTEMPT_THRESHOLD =
        'davidbel_ai_search_data_processing/vector_deletion/upsert_attempt_threshold';
    private const string XML_PATH_DELETION_MAXIMUM_RUNTIME_SECONDS =
        'davidbel_ai_search_data_processing/vector_deletion/maximum_runtime_seconds';
    private const string XML_PATH_RETRY_ATTEMPT_THRESHOLD =
        'davidbel_ai_search_data_processing/retry/attempt_threshold';
    private const string XML_PATH_CLEANUP_ATTEMPT_THRESHOLD =
        'davidbel_ai_search_data_processing/cleanup/attempt_threshold';
    private const string XML_PATH_CLEANUP_RESULT_RETENTION_HOURS =
        'davidbel_ai_search_data_processing/cleanup/result_retention';
    private const string XML_PATH_INDEXER_LOCK_TIMEOUT_SECONDS =
        'davidbel_ai_search_data_processing/indexer/lock_timeout_seconds';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @return positive-int
     */
    public function getDocumentProcessingBatchSize(): int
    {
        return $this->getPositiveInteger(self::XML_PATH_DOCUMENT_PROCESSING_BATCH_SIZE);
    }

    public function getVectorEmbeddingBatchSize(): int
    {
        return $this->getPositiveInteger(self::XML_PATH_VECTOR_EMBEDDING_BATCH_SIZE);
    }

    public function getVectorEmbeddingConcurrentRequests(): int
    {
        return $this->getPositiveInteger(self::XML_PATH_CONCURRENT_EMBEDDING_REQUESTS);
    }

    public function getVectorEmbeddingMaximumRuntimeSeconds(): int
    {
        return $this->getPositiveInteger(self::XML_PATH_EMBEDDING_MAXIMUM_RUNTIME_SECONDS);
    }

    public function getVectorDeletionBatchSize(): int
    {
        return $this->getPositiveInteger(self::XML_PATH_VECTOR_DELETION_BATCH_SIZE);
    }

    public function getVectorDeletionUpsertAttemptThreshold(): int
    {
        return $this->getPositiveInteger(self::XML_PATH_DELETION_UPSERT_ATTEMPT_THRESHOLD);
    }

    public function getVectorDeletionMaximumRuntimeSeconds(): int
    {
        return $this->getPositiveInteger(self::XML_PATH_DELETION_MAXIMUM_RUNTIME_SECONDS);
    }

    public function getRetryAttemptThreshold(): int
    {
        return $this->getPositiveInteger(self::XML_PATH_RETRY_ATTEMPT_THRESHOLD);
    }

    public function getCleanupAttemptThreshold(): int
    {
        return $this->getPositiveInteger(self::XML_PATH_CLEANUP_ATTEMPT_THRESHOLD);
    }

    public function getCleanupResultRetentionHours(): int
    {
        return $this->getPositiveInteger(self::XML_PATH_CLEANUP_RESULT_RETENTION_HOURS);
    }

    public function getIndexerLockTimeoutSeconds(): int
    {
        return $this->getPositiveInteger(self::XML_PATH_INDEXER_LOCK_TIMEOUT_SECONDS);
    }

    /**
     * @return positive-int
     */
    private function getPositiveInteger(string $path): int
    {
        $value = filter_var($this->scopeConfig->getValue($path), FILTER_VALIDATE_INT);

        if (!is_int($value) || $value < 1) {
            throw new UnexpectedValueException(
                sprintf('Configuration path "%s" must contain a positive integer.', $path)
            );
        }

        return $value;
    }
}
