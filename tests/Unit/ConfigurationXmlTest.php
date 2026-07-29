<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit;

use DOMDocument;
use DOMXPath;
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
        yield 'cron' => [
            'crontab.xml',
            'urn:magento:module:Magento_Cron:etc/crontab.xsd',
        ];
        yield 'cron groups' => [
            'cron_groups.xml',
            'urn:magento:module:Magento_Cron:etc/cron_groups.xsd',
        ];
        yield 'database schema' => [
            'db_schema.xml',
            'urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd',
        ];
        yield 'dependency injection' => [
            'di.xml',
            'urn:magento:framework:ObjectManager/etc/config.xsd',
        ];
        yield 'indexer' => [
            'indexer.xml',
            'urn:magento:framework:Indexer/etc/indexer.xsd',
        ];
        yield 'module' => [
            'module.xml',
            'urn:magento:framework:Module/etc/module.xsd',
        ];
        yield 'materialized view' => [
            'mview.xml',
            'urn:magento:framework:Mview/etc/mview.xsd',
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

    public function testVectorEmbeddingCronUsesDedicatedProcessGroup(): void
    {
        $configurationDirectory = dirname(__DIR__, 2) . '/src/etc/';
        $crontab = new DOMDocument();
        $cronGroups = new DOMDocument();

        self::assertTrue($crontab->load($configurationDirectory . 'crontab.xml'));
        self::assertTrue($cronGroups->load($configurationDirectory . 'cron_groups.xml'));

        $crontabXPath = new DOMXPath($crontab);
        $cronGroupsXPath = new DOMXPath($cronGroups);

        self::assertSame(
            1.0,
            $crontabXPath->evaluate(
                'count(/config/group[@id="davidbel_ai_search"]'
                . '/job[@name="davidbel_ai_search_vector_embedding"'
                . ' and @instance="DavidBel\AiSearch\Cron\VectorEmbedding"'
                . ' and @method="execute"])'
            )
        );
        self::assertSame(
            '1',
            $cronGroupsXPath->evaluate(
                'string(/config/group[@id="davidbel_ai_search"]/use_separate_process)'
            )
        );
    }

    public function testDeclaresMagentoCronModuleDependency(): void
    {
        $module = new DOMDocument();

        self::assertTrue(
            $module->load(dirname(__DIR__, 2) . '/src/etc/module.xml')
        );

        $moduleXPath = new DOMXPath($module);

        self::assertSame(
            1.0,
            $moduleXPath->evaluate(
                'count(/config/module[@name="DavidBel_AiSearch"]'
                . '/sequence/module[@name="Magento_Cron"])'
            )
        );
    }

    public function testDeclaresOpenAiEmbedderClientPreference(): void
    {
        $dependencyInjection = new DOMDocument();

        self::assertTrue(
            $dependencyInjection->load(dirname(__DIR__, 2) . '/src/etc/di.xml')
        );

        $dependencyInjectionXPath = new DOMXPath($dependencyInjection);

        self::assertSame(
            1.0,
            $dependencyInjectionXPath->evaluate(
                'count(/config/preference'
                . '[@for="DavidBel\AiSearch\Api\EmbedderClientInterface"]'
                . '[@type="DavidBel\AiSearch\Embedding\Client\OpenAi"])'
            )
        );
    }
}
