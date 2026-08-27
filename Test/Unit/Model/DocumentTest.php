<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Model;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Model\Document;
use DavidBel\AiSearch\Model\ResourceModel\Document as DocumentResource;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class DocumentTest extends TestCase
{
    private Document $document;

    protected function setUp(): void
    {
        $this->document = new Document(
            self::createStub(Context::class),
            self::createStub(Registry::class),
            self::createStub(ExtensionAttributesFactory::class),
            self::createStub(AttributeValueFactory::class),
            self::createStub(DocumentResource::class)
        );
    }

    public function testMapsPersistedDataToTheServiceContract(): void
    {
        $this->document->setData(DocumentInterface::DOCUMENT_ID, '12');
        $this->document->setDocumentId(12);
        $this->document->setSourceEntityType('product');
        $this->document->setSourceEntityId(42);
        $this->document->setStoreId(3);
        $this->document->setSourceCode('description');
        $this->document->setTitle('Product title');
        $this->document->setSourceHash(str_repeat('a', 64));
        $this->document->setCreatedAt('2026-07-28 10:00:00');
        $this->document->setUpdatedAt('2026-07-28 11:00:00');

        self::assertSame(12, $this->document->getDocumentId());
        self::assertSame('product', $this->document->getSourceEntityType());
        self::assertSame(42, $this->document->getSourceEntityId());
        self::assertSame(3, $this->document->getStoreId());
        self::assertSame('description', $this->document->getSourceCode());
        self::assertSame('Product title', $this->document->getTitle());
        self::assertSame(str_repeat('a', 64), $this->document->getSourceHash());
        self::assertSame('2026-07-28 10:00:00', $this->document->getCreatedAt());
        self::assertSame('2026-07-28 11:00:00', $this->document->getUpdatedAt());
    }

    public function testReturnsNullForUnsetOptionalValues(): void
    {
        self::assertNull($this->document->getDocumentId());
        self::assertNull($this->document->getTitle());
        self::assertNull($this->document->getCreatedAt());
        self::assertNull($this->document->getUpdatedAt());
        self::assertSame($this->document, $this->document->setTitle(null));
    }

    public function testRejectsInvalidOptionalString(): void
    {
        $this->document->setData(DocumentInterface::TITLE, 10);

        $this->expectException(UnexpectedValueException::class);
        $this->document->getTitle();
    }

    public function testRejectsInvalidRequiredString(): void
    {
        $this->document->setData(DocumentInterface::SOURCE_CODE, 10);

        $this->expectException(UnexpectedValueException::class);
        $this->document->getSourceCode();
    }

    public function testRejectsInvalidPersistedData(): void
    {
        $this->document->setData(DocumentInterface::SOURCE_ENTITY_ID, 'not-an-integer');

        $this->expectException(UnexpectedValueException::class);

        $this->document->getSourceEntityId();
    }
}
