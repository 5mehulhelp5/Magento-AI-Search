<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Config\EmbeddedAttributesConfig;

use DavidBel\AiSearch\Config\EmbeddedAttributesConfig\Documents;
use InvalidArgumentException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class DocumentsTest extends TestCase
{
    private const string CONFIGURATION_PATH =
        'davidbel_ai_search_semantic_search_source/document_configuration/documents';

    public function testBuildsAndCachesConfiguredDocuments(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())
            ->method('getValue')
            ->with(self::CONFIGURATION_PATH)
            ->willReturn('serialized documents');
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())
            ->method('unserialize')
            ->with('serialized documents')
            ->willReturn([
                '__empty' => [],
                'description' => [
                    'attribute_code' => ' description ',
                    'composite' => '0',
                    'parsing_strategy' => ' text_as_is ',
                ],
                'specification' => [
                    'attribute_code' => 'specification',
                    'composite' => '1',
                    'parsing_strategy' => 'html_to_markdown',
                ],
            ]);
        $documents = new Documents($scopeConfig, $serializer);

        $configuredDocuments = $documents->get();

        self::assertSame($configuredDocuments, $documents->get());
        self::assertCount(2, $configuredDocuments);
        self::assertSame('description', $configuredDocuments[0]->attributeCode);
        self::assertFalse($configuredDocuments[0]->composite);
        self::assertSame('text_as_is', $configuredDocuments[0]->parsingStrategy);
        self::assertNull($configuredDocuments[0]->template);
        self::assertNull($configuredDocuments[0]->children);
        self::assertSame('specification', $configuredDocuments[1]->attributeCode);
        self::assertTrue($configuredDocuments[1]->composite);
        self::assertSame('html_to_markdown', $configuredDocuments[1]->parsingStrategy);
    }

    public function testRejectsDuplicateDocumentAttributeCodes(): void
    {
        $scopeConfig = self::createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('serialized documents');
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('unserialize')->willReturn([
            ['attribute_code' => 'description', 'composite' => '0', 'parsing_strategy' => 'text_as_is'],
            ['attribute_code' => 'description', 'composite' => '1', 'parsing_strategy' => 'text_as_is'],
        ]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Document attribute code "description" is configured more than once.'
        );

        (new Documents($scopeConfig, $serializer))->get();
    }

    public function testWrapsInvalidSerializedConfiguration(): void
    {
        $scopeConfig = self::createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('invalid serialization');
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('unserialize')
            ->willThrowException(new InvalidArgumentException('invalid'));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'does not contain valid serialized Documents.'
        );

        (new Documents($scopeConfig, $serializer))->get();
    }

    public function testReturnsEmptyDocumentsForEmptyConfiguration(): void
    {
        $scopeConfig = self::createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(null);

        self::assertSame(
            [],
            (new Documents($scopeConfig, self::createStub(SerializerInterface::class)))->get()
        );
    }

    #[DataProvider('invalidDocuments')]
    public function testRejectsInvalidDocuments(mixed $configuration, mixed $rows, string $message): void
    {
        $scopeConfig = self::createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($configuration);
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('unserialize')->willReturn($rows);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        (new Documents($scopeConfig, $serializer))->get();
    }

    /**
     * @return array<string, array{mixed, mixed, string}>
     */
    public static function invalidDocuments(): array
    {
        return [
            'configuration type' => [10, [], 'must contain serialized Documents'],
            'serialized type' => ['configuration', 'row', 'must contain a Documents array'],
            'row type' => ['configuration', [10], 'configuration row must be an array'],
            'attribute' => ['configuration', [self::row('')], 'field "attribute_code"'],
            'strategy' => ['configuration', [self::row('sku', 0, null)], 'field "parsing_strategy"'],
            'composition' => ['configuration', [self::row('sku', 3)], 'composition value'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(
        mixed $attributeCode,
        mixed $composite = 0,
        mixed $strategy = 'text'
    ): array {
        return [
            'attribute_code' => $attributeCode,
            'composite' => $composite,
            'parsing_strategy' => $strategy,
        ];
    }
}
