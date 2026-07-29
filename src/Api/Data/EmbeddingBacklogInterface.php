<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Api\Data;

use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * AI search embedding backlog data.
 *
 * @api
 */
interface EmbeddingBacklogInterface extends ExtensibleDataInterface
{
    public const string BACKLOG_ID = 'backlog_id';
    public const string CHUNK_ID = 'chunk_id';
    public const string OPERATION = 'operation';
    public const string STATUS = 'status';
    public const string ATTEMPT_COUNT = 'attempt_count';
    public const string LAST_ERROR_CATEGORY = 'last_error_category';
    public const string CREATED_AT = 'created_at';
    public const string UPDATED_AT = 'updated_at';

    /**
     * Get the internal backlog ID.
     *
     * @return int|null
     */
    public function getBacklogId(): ?int;

    /**
     * Set the internal backlog ID.
     *
     * @param int $backlogId
     * @return \DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface
     */
    public function setBacklogId(int $backlogId): EmbeddingBacklogInterface;

    /**
     * Get the chunk ID.
     *
     * @return int
     */
    public function getChunkId(): int;

    /**
     * Set the chunk ID.
     *
     * @param int $chunkId
     * @return \DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface
     */
    public function setChunkId(int $chunkId): EmbeddingBacklogInterface;

    /**
     * Get the backlog operation.
     *
     * @return \DavidBel\AiSearch\Model\EmbeddingBacklog\Operation
     */
    public function getOperation(): Operation;

    /**
     * Set the backlog operation.
     *
     * @param \DavidBel\AiSearch\Model\EmbeddingBacklog\Operation $operation
     * @return \DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface
     */
    public function setOperation(Operation $operation): EmbeddingBacklogInterface;

    /**
     * Get the backlog status.
     *
     * @return \DavidBel\AiSearch\Model\EmbeddingBacklog\Status
     */
    public function getStatus(): Status;

    /**
     * Set the backlog status.
     *
     * @param \DavidBel\AiSearch\Model\EmbeddingBacklog\Status $status
     * @return \DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface
     */
    public function setStatus(Status $status): EmbeddingBacklogInterface;

    /**
     * Get the processing attempt count.
     *
     * @return int
     */
    public function getAttemptCount(): int;

    /**
     * Set the processing attempt count.
     *
     * @param int $attemptCount
     * @return \DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface
     */
    public function setAttemptCount(int $attemptCount): EmbeddingBacklogInterface;

    /**
     * Get the last error category.
     *
     * @return string|null
     */
    public function getLastErrorCategory(): ?string;

    /**
     * Set the last error category.
     *
     * @param string|null $lastErrorCategory
     * @return \DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface
     */
    public function setLastErrorCategory(?string $lastErrorCategory): EmbeddingBacklogInterface;

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
     * @return \DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface
     */
    public function setCreatedAt(string $createdAt): EmbeddingBacklogInterface;

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
     * @return \DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface
     */
    public function setUpdatedAt(string $updatedAt): EmbeddingBacklogInterface;
}
