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
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testBuildsDefaultDocumentWithBooleanComposition(): void
    {
        $scopeConfig = self::createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true);
        $scopeConfig->method('getValue')->willReturn('configuration');
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('unserialize')->willReturn([
            ['attribute_code' => ['sku'], 'composite' => false, 'parsing_strategy' => 'text', 'template' => '{sku}'],
        ]);
        $document = (new DynamicDocument($scopeConfig, $serializer))->get();

        self::assertNotNull($document);
        self::assertNotNull($document->children);
        self::assertCount(1, $document->children);
        self::assertFalse($document->children[0]->composite);
    }

    #[DataProvider('invalidConfigurationValues')]
    public function testRejectsInvalidConfigurationValues(mixed $value, string $message): void
    {
        $scopeConfig = self::createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true);
        $scopeConfig->method('getValue')->willReturn($value);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        (new DynamicDocument($scopeConfig, self::createStub(SerializerInterface::class)))->get();
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidConfigurationValues(): array
    {
        return [
            'null' => [null, 'must contain a serialized Dynamic Document'],
            'blank' => ['  ', 'must contain a serialized Dynamic Document'],
        ];
    }

    public function testWrapsInvalidSerialization(): void
    {
        $scopeConfig = self::createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true);
        $scopeConfig->method('getValue')->willReturn('invalid');
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('unserialize')
            ->willThrowException(new \InvalidArgumentException('invalid'));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('does not contain a valid serialized Dynamic Document');

        (new DynamicDocument($scopeConfig, $serializer))->get();
    }

    #[DataProvider('invalidRows')]
    public function testRejectsInvalidRows(mixed $rows, string $message): void
    {
        $scopeConfig = self::createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true);
        $scopeConfig->method('getValue')->willReturn('configuration');
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('unserialize')->willReturn($rows);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        (new DynamicDocument($scopeConfig, $serializer))->get();
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidRows(): array
    {
        return [
            'serialized scalar' => ['row', 'must contain a Dynamic Document array'],
            'row scalar' => [[1], 'configuration row must be an array'],
            'attribute scalar' => [[self::row(10)], 'must contain one or more product attributes'],
            'attribute empty array' => [[self::row([])], 'must contain one or more product attributes'],
            'attribute empty string' => [[self::row([''])], 'code must be a non-empty string'],
            'attribute non-string' => [[self::row([10])], 'code must be a non-empty string'],
            'composition' => [[self::row(['sku'], 2)], 'composition value must be either zero or one'],
            'strategy' => [[self::row(['sku'], 0, '')], 'field "parsing_strategy"'],
            'template' => [[self::row(['sku'], 0, 'text', null)], 'field "template"'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(
        mixed $attributeCode,
        mixed $composite = 0,
        mixed $strategy = 'text',
        mixed $template = '{value}'
    ): array {
        return [
            'attribute_code' => $attributeCode,
            'composite' => $composite,
            'parsing_strategy' => $strategy,
            'template' => $template,
        ];
    }
}
