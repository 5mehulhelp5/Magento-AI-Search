<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * AI search document chunk data.
 *
 * @api
 */
interface ChunkInterface extends ExtensibleDataInterface
{
    public const string CHUNK_ID = 'chunk_id';
    public const string DOCUMENT_ID = 'document_id';
    public const string CHUNK_INDEX = 'chunk_index';
    public const string CONTENT = 'content';
    public const string CONTENT_HASH = 'content_hash';
    public const string CREATED_AT = 'created_at';
    public const string UPDATED_AT = 'updated_at';

    /**
     * Get the internal chunk ID.
     *
     * @return int|null
     */
    public function getChunkId(): ?int;

    /**
     * Set the internal chunk ID.
     *
     * @param int $chunkId
     * @return \DavidBel\AiSearch\Api\Data\ChunkInterface
     */
    public function setChunkId(int $chunkId): ChunkInterface;

    /**
     * Get the parent document ID.
     *
     * @return int
     */
    public function getDocumentId(): int;

    /**
     * Set the parent document ID.
     *
     * @param int $documentId
     * @return \DavidBel\AiSearch\Api\Data\ChunkInterface
     */
    public function setDocumentId(int $documentId): ChunkInterface;

    /**
     * Get the zero-based chunk index.
     *
     * @return int
     */
    public function getChunkIndex(): int;

    /**
     * Set the zero-based chunk index.
     *
     * @param int $chunkIndex
     * @return \DavidBel\AiSearch\Api\Data\ChunkInterface
     */
    public function setChunkIndex(int $chunkIndex): ChunkInterface;

    /**
     * Get the normalized chunk content.
     *
     * @return string
     */
    public function getContent(): string;

    /**
     * Set the normalized chunk content.
     *
     * @param string $content
     * @return \DavidBel\AiSearch\Api\Data\ChunkInterface
     */
    public function setContent(string $content): ChunkInterface;

    /**
     * Get the chunk SHA-256 hash.
     *
     * @return string
     */
    public function getContentHash(): string;

    /**
     * Set the chunk SHA-256 hash.
     *
     * @param string $contentHash
     * @return \DavidBel\AiSearch\Api\Data\ChunkInterface
     */
    public function setContentHash(string $contentHash): ChunkInterface;

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
     * @return \DavidBel\AiSearch\Api\Data\ChunkInterface
     */
    public function setCreatedAt(string $createdAt): ChunkInterface;

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
     * @return \DavidBel\AiSearch\Api\Data\ChunkInterface
     */
    public function setUpdatedAt(string $updatedAt): ChunkInterface;
}
