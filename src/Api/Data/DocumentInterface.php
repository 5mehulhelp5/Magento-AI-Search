<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * AI search document data.
 *
 * @api
 */
interface DocumentInterface extends ExtensibleDataInterface
{
    public const string DOCUMENT_ID = 'document_id';
    public const string SOURCE_ENTITY_TYPE = 'source_entity_type';
    public const string SOURCE_ENTITY_ID = 'source_entity_id';
    public const string STORE_ID = 'store_id';
    public const string SOURCE_CODE = 'source_code';
    public const string SOURCE_HASH = 'source_hash';
    public const string CREATED_AT = 'created_at';
    public const string UPDATED_AT = 'updated_at';

    /**
     * Get the internal document ID.
     *
     * @return int|null
     */
    public function getDocumentId(): ?int;

    /**
     * Set the internal document ID.
     *
     * @param int $documentId
     * @return \DavidBel\AiSearch\Api\Data\DocumentInterface
     */
    public function setDocumentId(int $documentId): DocumentInterface;

    /**
     * Get the source entity type.
     *
     * @return string
     */
    public function getSourceEntityType(): string;

    /**
     * Set the source entity type.
     *
     * @param string $sourceEntityType
     * @return \DavidBel\AiSearch\Api\Data\DocumentInterface
     */
    public function setSourceEntityType(string $sourceEntityType): DocumentInterface;

    /**
     * Get the source entity ID.
     *
     * @return int
     */
    public function getSourceEntityId(): int;

    /**
     * Set the source entity ID.
     *
     * @param int $sourceEntityId
     * @return \DavidBel\AiSearch\Api\Data\DocumentInterface
     */
    public function setSourceEntityId(int $sourceEntityId): DocumentInterface;

    /**
     * Get the store ID.
     *
     * @return int
     */
    public function getStoreId(): int;

    /**
     * Set the store ID.
     *
     * @param int $storeId
     * @return \DavidBel\AiSearch\Api\Data\DocumentInterface
     */
    public function setStoreId(int $storeId): DocumentInterface;

    /**
     * Get the source code.
     *
     * @return string
     */
    public function getSourceCode(): string;

    /**
     * Set the source code.
     *
     * @param string $sourceCode
     * @return \DavidBel\AiSearch\Api\Data\DocumentInterface
     */
    public function setSourceCode(string $sourceCode): DocumentInterface;

    /**
     * Get the resolved source value SHA-256 hash.
     *
     * @return string
     */
    public function getSourceHash(): string;

    /**
     * Set the resolved source value SHA-256 hash.
     *
     * @param string $sourceHash
     * @return \DavidBel\AiSearch\Api\Data\DocumentInterface
     */
    public function setSourceHash(string $sourceHash): DocumentInterface;

    /**
     * Get the creation timestamp.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set the creation timestamp.
     *
     * @param string $createdAt
     * @return \DavidBel\AiSearch\Api\Data\DocumentInterface
     */
    public function setCreatedAt(string $createdAt): DocumentInterface;

    /**
     * Get the update timestamp.
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Set the update timestamp.
     *
     * @param string $updatedAt
     * @return \DavidBel\AiSearch\Api\Data\DocumentInterface
     */
    public function setUpdatedAt(string $updatedAt): DocumentInterface;
}
