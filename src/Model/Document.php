<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Model\ResourceModel\Document as DocumentResource;
use Magento\Framework\Model\AbstractExtensibleModel;
use UnexpectedValueException;

class Document extends AbstractExtensibleModel implements DocumentInterface
{
    protected function _construct(): void
    {
        $this->_init(DocumentResource::class);
    }

    public function getDocumentId(): ?int
    {
        return $this->getNullableInteger(self::DOCUMENT_ID);
    }

    public function setDocumentId(int $documentId): DocumentInterface
    {
        return $this->setData(self::DOCUMENT_ID, $documentId);
    }

    public function getSourceEntityType(): string
    {
        return $this->getString(self::SOURCE_ENTITY_TYPE);
    }

    public function setSourceEntityType(string $sourceEntityType): DocumentInterface
    {
        return $this->setData(self::SOURCE_ENTITY_TYPE, $sourceEntityType);
    }

    public function getSourceEntityId(): int
    {
        return $this->getInteger(self::SOURCE_ENTITY_ID);
    }

    public function setSourceEntityId(int $sourceEntityId): DocumentInterface
    {
        return $this->setData(self::SOURCE_ENTITY_ID, $sourceEntityId);
    }

    public function getStoreId(): int
    {
        return $this->getInteger(self::STORE_ID);
    }

    public function setStoreId(int $storeId): DocumentInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function getSourceCode(): string
    {
        return $this->getString(self::SOURCE_CODE);
    }

    public function setSourceCode(string $sourceCode): DocumentInterface
    {
        return $this->setData(self::SOURCE_CODE, $sourceCode);
    }

    public function getTitle(): ?string
    {
        return $this->getNullableString(self::TITLE);
    }

    public function setTitle(?string $title): DocumentInterface
    {
        return $this->setData(self::TITLE, $title);
    }

    public function getSourceHash(): string
    {
        return $this->getString(self::SOURCE_HASH);
    }

    public function setSourceHash(string $sourceHash): DocumentInterface
    {
        return $this->setData(self::SOURCE_HASH, $sourceHash);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getNullableString(self::CREATED_AT);
    }

    public function setCreatedAt(string $createdAt): DocumentInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getNullableString(self::UPDATED_AT);
    }

    public function setUpdatedAt(string $updatedAt): DocumentInterface
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
            throw new UnexpectedValueException(sprintf('Document field "%s" is not a string.', $key));
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
            throw new UnexpectedValueException(sprintf('Document field "%s" is not a string.', $key));
        }

        return $value;
    }

    private function toInteger(mixed $value, string $key): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false) {
            throw new UnexpectedValueException(sprintf('Document field "%s" is not an integer.', $key));
        }

        return $integer;
    }
}
