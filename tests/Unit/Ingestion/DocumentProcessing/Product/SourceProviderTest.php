<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\DocumentProcessing\Product;

use DavidBel\AiSearch\Config\EmbeddedAttributesConfig;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentSource;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentSource\StoreScopedSource;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\AttributeValueProvider;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\DirectSourceBuilder;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\Eligibility;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\EmbeddingTemplate;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use InvalidArgumentException;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class SourceProviderTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    public function testLoadsProductIdsUsingTheProductResource(): void
    {
        $select = $this->createMock(Select::class);
        $select->expects(self::once())
            ->method('from')
            ->with('catalog_product_entity', ['entity_id'])
            ->willReturnSelf();
        $select->expects(self::once())->method('where')->with('entity_id > ?', 10)->willReturnSelf();
        $select->expects(self::once())->method('order')->with('entity_id ASC')->willReturnSelf();
        $select->expects(self::once())->method('limit')->with(200)->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchCol')->with($select)->willReturn(['11', 12]);
        $productResource = $this->createMock(ProductResource::class);
        $productResource->expects(self::once())->method('getConnection')->willReturn($connection);
        $productResource->expects(self::once())
            ->method('getEntityTable')
            ->willReturn('catalog_product_entity');

        self::assertSame(
            [11, 12],
            $this->createProvider($this->createCollectionFactory($productResource))
                ->getProductIdsAfter(10, 200)
        );
    }

    public function testRejectsANonPositiveBatchLimitBeforeLoadingAResource(): void
    {
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::never())->method('create');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The product batch limit must be positive.');

        $this->createProvider($collectionFactory)->getProductIdsAfter(0, 0);
    }

    public function testReturnsImmediatelyForEmptyProductIds(): void
    {
        $eligibility = $this->createMock(Eligibility::class);
        $eligibility->expects(self::never())->method('getEligibleScopesByProductIds');

        self::assertSame(
            [],
            $this->createProvider(
                self::createStub(CollectionFactory::class),
                $eligibility
            )->getSourcesByProductIds([])
        );
    }

    public function testMergesDirectAndTemplateSourcesForRequestedProducts(): void
    {
        $directSource = new DocumentSource(
            'description',
            'text_as_is',
            [new StoreScopedSource(1, 'Description')]
        );
        $templateSource = new DocumentSource(
            'embedding_template',
            'text_as_is',
            [new StoreScopedSource(1, 'Template')]
        );
        $eligibility = $this->createMock(Eligibility::class);
        $eligibility->expects(self::once())
            ->method('getEligibleScopesByProductIds')
            ->with([10, 20])
            ->willReturn([]);
        $config = $this->createMock(EmbeddedAttributesConfig::class);
        $config->expects(self::once())->method('getAttributes')->willReturn([]);
        $config->expects(self::once())->method('isDocumentTitleEnabled')->willReturn(false);
        $attributeValues = $this->createMock(AttributeValueProvider::class);
        $attributeValues->expects(self::once())
            ->method('getValuesBySourceCode')
            ->with([], [])
            ->willReturn([]);
        $directBuilder = $this->createMock(DirectSourceBuilder::class);
        $directBuilder->expects(self::once())
            ->method('buildSourcesByProductId')
            ->willReturn([10 => [$directSource]]);
        $template = $this->createMock(EmbeddingTemplate::class);
        $template->expects(self::once())
            ->method('buildSourcesByProductId')
            ->willReturn([20 => [$templateSource]]);

        self::assertSame(
            [10 => [$directSource], 20 => [$templateSource]],
            $this->createProvider(
                self::createStub(CollectionFactory::class),
                $eligibility,
                $config,
                $attributeValues,
                $directBuilder,
                $template
            )->getSourcesByProductIds([10, 20])
        );
    }

    public function testDelegatesAffectedProductResolutionToEligibility(): void
    {
        $eligibility = $this->createMock(Eligibility::class);
        $eligibility->expects(self::once())
            ->method('getAffectedProductIds')
            ->with([10, 20])
            ->willReturn([10, 21]);

        self::assertSame(
            [10, 21],
            $this->createProvider(
                self::createStub(CollectionFactory::class),
                $eligibility
            )->getAffectedProductIds([10, 20])
        );
    }

    private function createProvider(
        CollectionFactory $collectionFactory,
        ?Eligibility $eligibility = null,
        ?EmbeddedAttributesConfig $config = null,
        ?AttributeValueProvider $attributeValueProvider = null,
        ?DirectSourceBuilder $directSourceBuilder = null,
        ?EmbeddingTemplate $embeddingTemplate = null
    ): SourceProvider {
        return new SourceProvider(
            $collectionFactory,
            $eligibility ?? self::createStub(Eligibility::class),
            $config ?? self::createStub(EmbeddedAttributesConfig::class),
            $attributeValueProvider ?? self::createStub(AttributeValueProvider::class),
            $directSourceBuilder ?? self::createStub(DirectSourceBuilder::class),
            $embeddingTemplate ?? self::createStub(EmbeddingTemplate::class)
        );
    }

    private function createCollectionFactory(
        ProductResource $productResource
    ): CollectionFactory {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('getEntity')->willReturn($productResource);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::once())->method('create')->willReturn($collection);

        return $collectionFactory;
    }
}
