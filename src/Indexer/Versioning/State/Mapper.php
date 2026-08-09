<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning\State;

use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex\QueryConfigurationSnapshot;
use DavidBel\AiSearch\Indexer\Versioning\State;
use DavidBel\AiSearch\Indexer\Versioning\Target;
use UnexpectedValueException;

class Mapper
{
    /**
     * @param array<array-key, mixed> $data
     */
    public function map(array $data): State
    {
        $active = $this->getOptionalArray($data, 'active');
        $target = $this->getOptionalArray($data, 'target');

        return new State(
            $active === null ? null : $this->mapPhysicalIndex($active),
            $target === null ? null : $this->mapTarget($target),
            $this->getCacheStatus($data)
        );
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>|null
     */
    private function getOptionalArray(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        if ($value !== null && !is_array($value)) {
            throw new UnexpectedValueException('The stored search index version state is invalid.');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function getCacheStatus(array $data): CacheStatus
    {
        $value = $data['cache_status'] ?? CacheStatus::Clean->value;

        if (!is_string($value)) {
            throw new UnexpectedValueException('The stored search index cache status is invalid.');
        }

        $status = CacheStatus::tryFrom($value);

        if ($status === null) {
            throw new UnexpectedValueException('The stored search index cache status is invalid.');
        }

        return $status;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function mapTarget(array $data): Target
    {
        $version = $data['version'] ?? null;
        $documentProcessingCompleted = $data['document_processing_completed'] ?? null;

        if (!is_array($version) || !is_bool($documentProcessingCompleted)) {
            throw new UnexpectedValueException('The stored target index version is invalid.');
        }

        return new Target(
            $this->mapPhysicalIndex($version),
            $documentProcessingCompleted
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function mapPhysicalIndex(array $data): PhysicalIndex
    {
        $number = $data['number'] ?? null;
        $indexName = $data['index_name'] ?? null;
        $configurationFingerprint = $data['configuration_fingerprint'] ?? null;
        $queryConfiguration = $data['query_configuration'] ?? null;

        if (!is_int($number)
            || !is_string($indexName)
            || !is_string($configurationFingerprint)
            || !is_array($queryConfiguration)
        ) {
            throw new UnexpectedValueException('The stored search index version is invalid.');
        }

        return new PhysicalIndex(
            $number,
            $indexName,
            $configurationFingerprint,
            $this->mapQueryConfiguration($queryConfiguration)
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function mapQueryConfiguration(array $data): QueryConfigurationSnapshot
    {
        $embeddingModel = $data['embedding_model'] ?? null;
        $vectorDimensions = $data['vector_dimensions'] ?? null;
        $queryTemplate = $data['query_template'] ?? null;

        if (!is_string($embeddingModel) || !is_int($vectorDimensions) || !is_string($queryTemplate)) {
            throw new UnexpectedValueException('The stored query configuration snapshot is invalid.');
        }

        return new QueryConfigurationSnapshot(
            $embeddingModel,
            $vectorDimensions,
            $queryTemplate
        );
    }
}
