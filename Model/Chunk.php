<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Model\ResourceModel\Chunk as ChunkResource;
use Magento\Framework\Model\AbstractExtensibleModel;
use UnexpectedValueException;

class Chunk extends AbstractExtensibleModel implements ChunkInterface
{
    protected function _construct(): void
    {
        $this->_init(ChunkResource::class);
    }

    public function getChunkId(): ?int
    {
        return $this->getNullableInteger(self::CHUNK_ID);
    }

    public function setChunkId(int $chunkId): ChunkInterface
    {
        return $this->setData(self::CHUNK_ID, $chunkId);
    }

    public function getDocumentId(): int
    {
        return $this->getInteger(self::DOCUMENT_ID);
    }

    public function setDocumentId(int $documentId): ChunkInterface
    {
        return $this->setData(self::DOCUMENT_ID, $documentId);
    }

    public function getChunkIndex(): int
    {
        return $this->getInteger(self::CHUNK_INDEX);
    }

    public function setChunkIndex(int $chunkIndex): ChunkInterface
    {
        return $this->setData(self::CHUNK_INDEX, $chunkIndex);
    }

    public function getContent(): string
    {
        return $this->getString(self::CONTENT);
    }

    public function setContent(string $content): ChunkInterface
    {
        return $this->setData(self::CONTENT, $content);
    }

    public function getContentHash(): string
    {
        return $this->getString(self::CONTENT_HASH);
    }

    public function setContentHash(string $contentHash): ChunkInterface
    {
        return $this->setData(self::CONTENT_HASH, $contentHash);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getNullableString(self::CREATED_AT);
    }

    public function setCreatedAt(string $createdAt): ChunkInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getNullableString(self::UPDATED_AT);
    }

    public function setUpdatedAt(string $updatedAt): ChunkInterface
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
            throw new UnexpectedValueException(sprintf('Chunk field "%s" is not a string.', $key));
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

    private function getString(string $key): string
    {
        $value = $this->getData($key);

        if (!is_string($value)) {
            throw new UnexpectedValueException(sprintf('Chunk field "%s" is not a string.', $key));
        }

        return $value;
    }

    private function toInteger(mixed $value, string $key): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false) {
            throw new UnexpectedValueException(sprintf('Chunk field "%s" is not an integer.', $key));
        }

        return $integer;
    }
}
