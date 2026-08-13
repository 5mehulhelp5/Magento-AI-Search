<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use Magento\Framework\Model\AbstractExtensibleModel;
use UnexpectedValueException;

class EmbeddingBacklog extends AbstractExtensibleModel implements EmbeddingBacklogInterface
{
    protected function _construct(): void
    {
        $this->_init(EmbeddingBacklogResource::class);
    }

    public function getBacklogId(): ?int
    {
        return $this->getNullableInteger(self::BACKLOG_ID);
    }

    public function setBacklogId(int $backlogId): EmbeddingBacklogInterface
    {
        return $this->setData(self::BACKLOG_ID, $backlogId);
    }

    public function getVersion(): int
    {
        return $this->getInteger(self::VERSION);
    }

    public function setVersion(int $version): EmbeddingBacklogInterface
    {
        return $this->setData(self::VERSION, $version);
    }

    public function getChunkId(): int
    {
        return $this->getInteger(self::CHUNK_ID);
    }

    public function setChunkId(int $chunkId): EmbeddingBacklogInterface
    {
        return $this->setData(self::CHUNK_ID, $chunkId);
    }

    public function getSourceEntityType(): ?string
    {
        return $this->getNullableString(self::SOURCE_ENTITY_TYPE);
    }

    public function setSourceEntityType(string $sourceEntityType): EmbeddingBacklogInterface
    {
        return $this->setData(self::SOURCE_ENTITY_TYPE, $sourceEntityType);
    }

    public function getSourceEntityId(): ?int
    {
        return $this->getNullableInteger(self::SOURCE_ENTITY_ID);
    }

    public function setSourceEntityId(int $sourceEntityId): EmbeddingBacklogInterface
    {
        return $this->setData(self::SOURCE_ENTITY_ID, $sourceEntityId);
    }

    public function getOperation(): Operation
    {
        $value = $this->getData(self::OPERATION);

        if ($value instanceof Operation) {
            return $value;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException('Embedding backlog operation is not a string.');
        }

        $operation = Operation::tryFrom($value);

        if ($operation === null) {
            throw new UnexpectedValueException(
                sprintf('Embedding backlog operation "%s" is invalid.', $value)
            );
        }

        return $operation;
    }

    public function setOperation(Operation $operation): EmbeddingBacklogInterface
    {
        return $this->setData(self::OPERATION, $operation->value);
    }

    public function getStatus(): Status
    {
        $value = $this->getData(self::STATUS);

        if ($value instanceof Status) {
            return $value;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException('Embedding backlog status is not a string.');
        }

        $status = Status::tryFrom($value);

        if ($status === null) {
            throw new UnexpectedValueException(
                sprintf('Embedding backlog status "%s" is invalid.', $value)
            );
        }

        return $status;
    }

    public function setStatus(Status $status): EmbeddingBacklogInterface
    {
        return $this->setData(self::STATUS, $status->value);
    }

    public function getAttemptCount(): int
    {
        return $this->getInteger(self::ATTEMPT_COUNT);
    }

    public function setAttemptCount(int $attemptCount): EmbeddingBacklogInterface
    {
        return $this->setData(self::ATTEMPT_COUNT, $attemptCount);
    }

    public function getLastErrorCategory(): ?string
    {
        return $this->getNullableString(self::LAST_ERROR_CATEGORY);
    }

    public function setLastErrorCategory(?string $lastErrorCategory): EmbeddingBacklogInterface
    {
        return $this->setData(self::LAST_ERROR_CATEGORY, $lastErrorCategory);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getNullableString(self::CREATED_AT);
    }

    public function setCreatedAt(string $createdAt): EmbeddingBacklogInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getNullableString(self::UPDATED_AT);
    }

    public function setUpdatedAt(string $updatedAt): EmbeddingBacklogInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    private function getNullableString(string $key): ?string
    {
        $value = $this->getData($key);

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException(
                sprintf('Embedding backlog field "%s" is not a string.', $key)
            );
        }

        return $value;
    }

    private function getNullableInteger(string $key): ?int
    {
        $value = $this->getData($key);

        if ($value === null) {
            return null;
        }

        return $this->toInteger($value, $key);
    }

    private function getInteger(string $key): int
    {
        return $this->toInteger($this->getData($key), $key);
    }

    private function toInteger(mixed $value, string $key): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false) {
            throw new UnexpectedValueException(
                sprintf('Embedding backlog field "%s" is not an integer.', $key)
            );
        }

        return $integer;
    }
}
