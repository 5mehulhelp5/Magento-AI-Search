<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Config\EmbeddedAttributesConfig;

use DavidBel\AiSearch\Config\EmbeddedAttributesConfig\DynamicDocument;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class DynamicDocumentTest extends TestCase
{
    private const string ENABLED_PATH =
        'davidbel_ai_search_search_source/document_configuration/enable_dynamic_document';
    private const string CONFIGURATION_PATH =
        'davidbel_ai_search_search_source/document_configuration/dynamic_document';

    public function testBuildsAndCachesAStoreScopedDynamicDocument(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())
            ->method('isSetFlag')
            ->with(self::ENABLED_PATH, ScopeInterface::SCOPE_STORE, 3)
            ->willReturn(true);
        $scopeConfig->expects(self::once())
            ->method('getValue')
            ->with(self::CONFIGURATION_PATH, ScopeInterface::SCOPE_STORE, 3)
            ->willReturn('serialized dynamic document');
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())
            ->method('unserialize')
            ->with('serialized dynamic document')
            ->willReturn([
                '__empty' => [],
                'part' => [
                    'attribute_code' => ' name, sku, name ',
                    'composite' => '1',
                    'parsing_strategy' => ' text_as_is ',
                    'template' => ' Product: {{name}} ',
                ],
            ]);
        $dynamicDocument = new DynamicDocument($scopeConfig, $serializer);

        $document = $dynamicDocument->get(3);

        self::assertNotNull($document);
        self::assertSame($document, $dynamicDocument->get(3));
        self::assertSame('embedding_template', $document->attributeCode);
        self::assertFalse($document->composite);
        self::assertSame('text_as_is', $document->parsingStrategy);
        self::assertNull($document->template);
        self::assertNotNull($document->children);
        self::assertCount(1, $document->children);
        self::assertSame('name,sku', $document->children[0]->attributeCode);
        self::assertTrue($document->children[0]->composite);
        self::assertSame('text_as_is', $document->children[0]->parsingStrategy);
        self::assertSame('Product: {{name}}', $document->children[0]->template);
    }

    public function testCachesADisabledDynamicDocument(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())
            ->method('isSetFlag')
            ->with(self::ENABLED_PATH)
            ->willReturn(false);
        $scopeConfig->expects(self::never())->method('getValue');
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('unserialize');
        $dynamicDocument = new DynamicDocument($scopeConfig, $serializer);

        self::assertNull($dynamicDocument->get());
        self::assertNull($dynamicDocument->get());
    }

    public function testRejectsAnEnabledDocumentWithoutParts(): void
    {
        $scopeConfig = self::createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true);
        $scopeConfig->method('getValue')->willReturn('serialized dynamic document');
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('unserialize')->willReturn(['__empty' => []]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'An enabled Dynamic Document must contain at least one configured part.'
        );

        (new DynamicDocument($scopeConfig, $serializer))->get(3);
    }

    public function testRejectsANonPositiveStoreId(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::never())->method('isSetFlag');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('A Dynamic Document store ID must be positive.');

        (new DynamicDocument(
            $scopeConfig,
            self::createStub(SerializerInterface::class)
        ))->get(0);
    }
}
