<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress;

use DavidBel\AiSearch\Tests\Stress\Support\CatalogDataset;
use DavidBel\AiSearch\Tests\Stress\Support\CatalogDataset\DescriptionGenerator;
use Magento\ConfigurableProduct\Api\LinkManagementInterface;

class DatasetCreationTest extends StressTestCase
{
    public function testCreatesDeterministicConfigurableProductDataset(): void
    {
        $dataset = $this->create(CatalogDataset::class);
        self::assertSame([], $dataset->getAllProductIds(), 'Run the cleanup stage before recreating the dataset.');

        $dataset->create();

        self::assertCount(CatalogDataset::TOTAL_PRODUCT_COUNT, $dataset->getAllProductIds());
        self::assertCount(
            CatalogDataset::CONFIGURABLE_PRODUCT_COUNT,
            $dataset->getConfigurableProductIds()
        );
        self::assertCount(
            CatalogDataset::CONFIGURABLE_PRODUCT_COUNT * CatalogDataset::SIMPLE_PRODUCTS_PER_CONFIGURABLE,
            $dataset->getSimpleProductIds()
        );
        $this->assertConfigurableRelations($dataset);
        $this->assertDescriptions($dataset);
    }

    private function assertConfigurableRelations(CatalogDataset $dataset): void
    {
        $linkManagement = $this->get(LinkManagementInterface::class);

        foreach ($dataset->getConfigurableProductSkus() as $sku) {
            self::assertCount(
                CatalogDataset::SIMPLE_PRODUCTS_PER_CONFIGURABLE,
                $linkManagement->getChildren($sku),
                sprintf('Configurable product "%s" does not have ten children.', $sku)
            );
        }
    }

    private function assertDescriptions(CatalogDataset $dataset): void
    {
        $descriptions = $dataset->getDescriptionsBySku();
        $descriptionHashes = [];

        self::assertCount(CatalogDataset::TOTAL_PRODUCT_COUNT, $descriptions);

        foreach ($descriptions as $sku => $description) {
            $characterCount = mb_strlen($description);
            self::assertGreaterThanOrEqual(
                DescriptionGenerator::MINIMUM_CHARACTER_COUNT,
                $characterCount,
                sprintf('Description for "%s" is shorter than 1,000 estimated tokens.', $sku)
            );
            self::assertLessThanOrEqual(
                DescriptionGenerator::MAXIMUM_CHARACTER_COUNT,
                $characterCount,
                sprintf('Description for "%s" is longer than 2,000 estimated tokens.', $sku)
            );
            $descriptionHashes[] = hash('sha256', $description);
        }

        self::assertCount(
            CatalogDataset::TOTAL_PRODUCT_COUNT,
            array_unique($descriptionHashes),
            'Every stress product description must be unique.'
        );
    }
}
