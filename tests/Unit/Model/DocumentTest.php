<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Model;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Model\Document;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use UnexpectedValueException;

class DocumentTest extends TestCase
{
    private Document $document;

    protected function setUp(): void
    {
        $reflection = new ReflectionClass(Document::class);
        $this->document = $reflection->newInstanceWithoutConstructor();
    }

    public function testMapsPersistedDataToTheServiceContract(): void
    {
        $this->document->setData(DocumentInterface::DOCUMENT_ID, '12');
        $this->document->setSourceEntityType('product');
        $this->document->setSourceEntityId(42);
        $this->document->setStoreId(3);
        $this->document->setSourceCode('description');
        $this->document->setSourceHash(str_repeat('a', 64));
        $this->document->setCreatedAt('2026-07-28 10:00:00');
        $this->document->setUpdatedAt('2026-07-28 11:00:00');

        self::assertSame(12, $this->document->getDocumentId());
        self::assertSame('product', $this->document->getSourceEntityType());
        self::assertSame(42, $this->document->getSourceEntityId());
        self::assertSame(3, $this->document->getStoreId());
        self::assertSame('description', $this->document->getSourceCode());
        self::assertSame(str_repeat('a', 64), $this->document->getSourceHash());
        self::assertSame('2026-07-28 10:00:00', $this->document->getCreatedAt());
        self::assertSame('2026-07-28 11:00:00', $this->document->getUpdatedAt());
    }

    public function testRejectsInvalidPersistedData(): void
    {
        $this->document->setData(DocumentInterface::SOURCE_ENTITY_ID, 'not-an-integer');

        $this->expectException(UnexpectedValueException::class);

        $this->document->getSourceEntityId();
    }
}
