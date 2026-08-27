<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit;

use DavidBel\AiSearch\Config\SearchConfig;
use DavidBel\AiSearch\Indexer\Versioning\IndexName;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex\QueryConfigurationSnapshot;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\Eligibility\EligibleScope;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\EmbeddingTemplate\AttributeData;
use DavidBel\AiSearch\Search\Candidates;
use InvalidArgumentException;
use Magento\Elasticsearch\Model\Config as MagentoSearchConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class ValueObjectsTest extends TestCase
{
    public function testExposesCandidateScores(): void
    {
        self::assertSame([10 => 0.9], (new Candidates([10 => 0.9]))->scoresByProductId);
    }

    public function testExposesEligibleScope(): void
    {
        $scope = new EligibleScope(2, [10, 20]);

        self::assertSame(2, $scope->storeId);
        self::assertSame([10, 20], $scope->sourceProductIds);
    }

    public function testExposesAttributeData(): void
    {
        $data = new AttributeData(
            [1 => [
                'code' => 'name',
                'backend_type' => 'varchar',
                'frontend_input' => 'text',
            ]],
            ['name' => ['entity_10' => 'Shoe']],
            [1 => [2 => 'Red']]
        );

        self::assertSame('name', $data->attributesById[1]['code']);
        self::assertSame('Shoe', $data->rawValues['name']['entity_10']);
        self::assertSame('Red', $data->optionLabels[1][2]);
    }

    public function testSerializesAQueryConfigurationSnapshot(): void
    {
        $snapshot = new QueryConfigurationSnapshot('model', 3, 'query: {text}');

        self::assertSame(
            [
                'embedding_model' => 'model',
                'vector_dimensions' => 3,
                'query_template' => 'query: {text}',
            ],
            $snapshot->toArray()
        );
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function invalidSnapshots(): array
    {
        return [
            'model' => ['', 3, 'query'],
            'dimensions' => ['model', 0, 'query'],
            'template' => ['model', 3, ''],
        ];
    }

    #[DataProvider('invalidSnapshots')]
    public function testRejectsInvalidQueryConfigurationSnapshots(
        string $model,
        int $dimensions,
        string $template
    ): void {
        $this->expectException(InvalidArgumentException::class);

        new QueryConfigurationSnapshot($model, $dimensions, $template);
    }

    public function testBuildsVersionedIndexNamesWithAndWithoutAPrefix(): void
    {
        $searchConfig = self::createStub(SearchConfig::class);
        $searchConfig->method('getIndexName')->willReturn('chunks');
        $magentoConfig = self::createStub(MagentoSearchConfig::class);
        $magentoConfig->method('getIndexPrefix')->willReturn(' store ');
        $indexName = new IndexName($magentoConfig, $searchConfig);

        self::assertSame('store_chunks', $indexName->getAlias());
        self::assertSame('store_chunks_v2', $indexName->getVersionName(2));

        $magentoConfigWithoutPrefix = self::createStub(MagentoSearchConfig::class);
        $magentoConfigWithoutPrefix->method('getIndexPrefix')->willReturn(' ');
        self::assertSame(
            'chunks',
            (new IndexName($magentoConfigWithoutPrefix, $searchConfig))->getAlias()
        );
    }

    public function testRejectsANonPositiveIndexVersion(): void
    {
        $this->expectException(UnexpectedValueException::class);

        (new IndexName(
            self::createStub(MagentoSearchConfig::class),
            self::createStub(SearchConfig::class)
        ))->getVersionName(0);
    }
}
