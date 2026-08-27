<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Config;

use DavidBel\AiSearch\Config\EmbeddedAttribute;
use DavidBel\AiSearch\Config\EmbeddedAttributesConfig;
use DavidBel\AiSearch\Config\EmbeddedAttributesConfig\Documents;
use DavidBel\AiSearch\Config\EmbeddedAttributesConfig\DynamicDocument;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class EmbeddedAttributesConfigTest extends TestCase
{
    public function testCombinesDocumentsWithTheStoreScopedDynamicDocument(): void
    {
        $document = new EmbeddedAttribute('description', false, 'text_as_is', null, null);
        $dynamicDocument = new EmbeddedAttribute(
            'embedding_template',
            false,
            'text_as_is',
            null,
            []
        );
        $documents = $this->createMock(Documents::class);
        $documents->expects(self::once())->method('get')->willReturn([$document]);
        $dynamic = $this->createMock(DynamicDocument::class);
        $dynamic->expects(self::once())
            ->method('get')
            ->with(3)
            ->willReturn($dynamicDocument);

        self::assertSame(
            [$document, $dynamicDocument],
            (new EmbeddedAttributesConfig(
                self::createStub(ScopeConfigInterface::class),
                $documents,
                $dynamic
            ))->getAttributes(3)
        );
    }

    public function testReturnsDocumentsWithoutADisabledDynamicDocument(): void
    {
        $document = new EmbeddedAttribute('description', false, 'text_as_is', null, null);
        $documents = self::createStub(Documents::class);
        $documents->method('get')->willReturn([$document]);
        $dynamic = self::createStub(DynamicDocument::class);
        $dynamic->method('get')->willReturn(null);

        self::assertSame(
            [$document],
            (new EmbeddedAttributesConfig(
                self::createStub(ScopeConfigInterface::class),
                $documents,
                $dynamic
            ))->getAttributes(3)
        );
    }

    public function testExposesTitleAndDynamicDocumentConfiguration(): void
    {
        $scopeConfig = self::createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true);
        $scopeConfig->method('getValue')->willReturn('name');
        $dynamicDocument = self::createStub(DynamicDocument::class);
        $dynamicDocument->method('isEnabled')->willReturn(true);
        $attribute = new EmbeddedAttribute('dynamic', false, 'text', null, []);
        $dynamicDocument->method('get')->willReturn($attribute);
        $config = new EmbeddedAttributesConfig(
            $scopeConfig,
            self::createStub(Documents::class),
            $dynamicDocument
        );

        self::assertTrue($config->isDocumentTitleEnabled());
        self::assertSame('name', $config->getDocumentTitleAttributeCode());
        self::assertTrue($config->isDynamicDocumentEnabled(2));
        self::assertSame($attribute, $config->getDynamicDocument(2));
    }

    public function testRejectsNonStringTitleAttribute(): void
    {
        $scopeConfig = self::createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(null);
        $config = new EmbeddedAttributesConfig(
            $scopeConfig,
            self::createStub(Documents::class),
            self::createStub(DynamicDocument::class)
        );

        $this->expectException(UnexpectedValueException::class);
        $config->getDocumentTitleAttributeCode();
    }
}
