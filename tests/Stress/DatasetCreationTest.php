<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress;

use DavidBel\AiSearch\Tests\Stress\Support\CatalogDataset;
use DavidBel\AiSearch\Tests\Stress\Support\CatalogDataset\DescriptionGenerator;
use DavidBel\AiSearch\Tests\Stress\Support\Measurement;
use DavidBel\AiSearch\Tests\Stress\Support\StressConfiguration;
use Magento\ConfigurableProduct\Api\LinkManagementInterface;

class DatasetCreationTest extends StressTestCase
{
    public function testCreatesDeterministicProductDataset(): void
    {
        $startedAt = microtime(true);
        $dataset = $this->create(CatalogDataset::class);
        $configuration = $this->create(StressConfiguration::class);
        self::assertSame([], $dataset->getAllProductIds(), 'Run the cleanup stage before recreating the dataset.');

        $dataset->create();

        self::assertCount($configuration->getTotalProductCount(), $dataset->getAllProductIds());
        self::assertCount(
            $configuration->getConfigurableProductCount(),
            $dataset->getConfigurableProductIds()
        );
        self::assertCount(
            $configuration->getSimpleProductCount(),
            $dataset->getSimpleProductIds()
        );
        $this->assertConfigurableRelations($dataset);
        $this->assertDescriptions($dataset);
        $duration = microtime(true) - $startedAt;
        $this->create(Measurement::class)->recordStage('dataset_creation', [
            'duration_seconds' => round($duration, 3),
            'products_created' => $configuration->getTotalProductCount(),
            'products_per_second' => round($configuration->getTotalProductCount() / $duration, 3),
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ]);
    }

    private function assertConfigurableRelations(CatalogDataset $dataset): void
    {
        $linkManagement = $this->get(LinkManagementInterface::class);
        $configuration = $this->create(StressConfiguration::class);

        foreach ($dataset->getConfigurableProductSkus() as $sku) {
            self::assertCount(
                $configuration->getSimpleProductsPerConfigurable(),
                $linkManagement->getChildren($sku),
                sprintf('Configurable product "%s" does not have the expected children.', $sku)
            );
        }
    }

    private function assertDescriptions(CatalogDataset $dataset): void
    {
        $descriptions = $dataset->getDescriptionsBySku();
        $descriptionHashes = [];

        $configuration = $this->create(StressConfiguration::class);
        self::assertCount($configuration->getTotalProductCount(), $descriptions);

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
            $configuration->getTotalProductCount(),
            array_unique($descriptionHashes),
            'Every stress product description must be unique.'
        );
    }
}
