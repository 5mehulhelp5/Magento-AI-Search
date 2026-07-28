<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit;

use DOMDocument;
use Magento\Framework\Config\Dom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigurationXmlTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function configurations(): iterable
    {
        yield 'database schema' => [
            'db_schema.xml',
            'urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd',
        ];
        yield 'dependency injection' => [
            'di.xml',
            'urn:magento:framework:ObjectManager/etc/config.xsd',
        ];
        yield 'module' => [
            'module.xml',
            'urn:magento:framework:Module/etc/module.xsd',
        ];
    }

    #[DataProvider('configurations')]
    public function testConfigurationMatchesMagentoSchema(string $fileName, string $schema): void
    {
        $document = new DOMDocument();
        $path = dirname(__DIR__, 2) . '/src/etc/' . $fileName;

        self::assertTrue($document->load($path));
        self::assertSame([], Dom::validateDomDocument($document, $schema));
    }
}
