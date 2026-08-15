<?php
/**
 * davidbel/magento-ai-search by David Belicza
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

    public function testChunkWorkflowsUseDedicatedProcessGroups(): void
    {
        $configurationDirectory = dirname(__DIR__, 2) . '/src/etc/';
        $crontab = new DOMDocument();
        $cronGroups = new DOMDocument();

        self::assertTrue($crontab->load($configurationDirectory . 'crontab.xml'));
        self::assertTrue($cronGroups->load($configurationDirectory . 'cron_groups.xml'));

        $crontabXPath = new DOMXPath($crontab);
        $cronGroupsXPath = new DOMXPath($cronGroups);

        $jobs = [
            'davidbel_ai_search' => [
                'davidbel_ai_search_chunk_processing' => 'DavidBel\AiSearch\Cron\ChunkProcessing',
            ],
            'davidbel_ai_search_maintenance' => [
                'davidbel_ai_search_chunk_processing_retry' => 'DavidBel\AiSearch\Cron\ChunkProcessingRetry',
                'davidbel_ai_search_chunk_processing_cleanup' => 'DavidBel\AiSearch\Cron\ChunkProcessingCleanup',
            ],
            'davidbel_ai_search_delete' => [
                'davidbel_ai_search_chunk_delete' => 'DavidBel\AiSearch\Cron\ChunkDelete',
            ],
        ];

        foreach ($jobs as $group => $groupJobs) {
            self::assertSame(
                '1',
                $cronGroupsXPath->evaluate(
                    sprintf('string(/config/group[@id="%s"]/use_separate_process)', $group)
                )
            );

            foreach ($groupJobs as $job => $instance) {
                self::assertSame(
                    1.0,
                    $crontabXPath->evaluate(
                        sprintf(
                            'count(/config/group[@id="%s"]/job[@name="%s"]'
                            . '[@instance="%s"][@method="execute"])',
                            $group,
                            $job,
                            $instance
                        )
                    )
                );
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function moduleDependencies(): iterable
    {
        yield 'cron' => ['Magento_Cron'];
        yield 'OpenSearch' => ['Magento_OpenSearch'];
    }

    #[DataProvider('moduleDependencies')]
    public function testDeclaresMagentoModuleDependency(string $moduleName): void
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
                . sprintf('/sequence/module[@name="%s"])', $moduleName)
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
                . '[@for="DavidBel\AiSearch\Client\Embedding\EmbedderClientInterface"]'
                . '[@type="DavidBel\AiSearch\Client\Embedding\OpenAi"])'
            )
        );
        self::assertSame(
            1.0,
            $dependencyInjectionXPath->evaluate(
                'count(/config/virtualType'
                . '[@name="DavidBel\AiSearch\Client\Embedding\HttpClient"]'
                . '[@type="GuzzleHttp\Client"])'
            )
        );
        self::assertSame(
            1.0,
            $dependencyInjectionXPath->evaluate(
                'count(/config/type'
                . '[@name="DavidBel\AiSearch\Client\Embedding\OpenAi"]'
                . '/arguments/argument[@name="client"]'
                . '[text()="DavidBel\AiSearch\Client\Embedding\HttpClient"])'
            )
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function embeddingBacklogPreferences(): iterable
    {
        yield 'data model' => [
            'DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface',
            'DavidBel\AiSearch\Model\EmbeddingBacklog',
        ];
        yield 'search results' => [
            'DavidBel\AiSearch\Api\Data\EmbeddingBacklogSearchResultsInterface',
            'DavidBel\AiSearch\Model\EmbeddingBacklogSearchResults',
        ];
        yield 'repository' => [
            'DavidBel\AiSearch\Api\EmbeddingBacklogRepositoryInterface',
            'DavidBel\AiSearch\Repository\EmbeddingBacklogRepository',
        ];
    }

    #[DataProvider('embeddingBacklogPreferences')]
    public function testDeclaresEmbeddingBacklogPreference(
        string $interface,
        string $implementation
    ): void {
        $dependencyInjection = new DOMDocument();

        self::assertTrue(
            $dependencyInjection->load(dirname(__DIR__, 2) . '/src/etc/di.xml')
        );

        $dependencyInjectionXPath = new DOMXPath($dependencyInjection);

        self::assertSame(
            1.0,
            $dependencyInjectionXPath->evaluate(
                sprintf(
                    'count(/config/preference[@for="%s"][@type="%s"])',
                    $interface,
                    $implementation
                )
            )
        );
    }

    public function testDefinesEmbeddingBacklogSchema(): void
    {
        $databaseSchema = new DOMDocument();

        self::assertTrue(
            $databaseSchema->load(dirname(__DIR__, 2) . '/src/etc/db_schema.xml')
        );

        $databaseSchemaXPath = new DOMXPath($databaseSchema);
        $tablePath = '/schema/table[@name="davidbel_ai_search_embedding_backlog"]';

        self::assertSame(
            1.0,
            $databaseSchemaXPath->evaluate(
                sprintf(
                    'count(%s'
                    . '[column[@name="backlog_id"][@identity="true"]]'
                    . '[column[@name="chunk_id"]]'
                    . '[column[@name="source_entity_type"]]'
                    . '[column[@name="source_entity_id"]]'
                    . '[column[@name="operation"][@default="upsert"]]'
                    . '[column[@name="status"][@default="pending"]]'
                    . '[column[@name="backlog_version"][@default="1"]]'
                    . '[column[@name="attempt_count"][@default="0"]]'
                    . '[constraint/column[@name="chunk_id"]'
                    . '/../column[@name="operation"]]'
                    . '[index/column[@name="operation"]'
                    . '/../column[@name="status"]])',
                    $tablePath
                )
            )
        );
    }
}
