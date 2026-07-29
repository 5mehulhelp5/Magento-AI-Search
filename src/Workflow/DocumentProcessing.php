<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow;

use DavidBel\AiSearch\Workflow\DocumentProcessing\DocumentUpdater;
use DavidBel\AiSearch\Workflow\DocumentProcessing\Product\SourceProvider;
use DavidBel\AiSearch\Workflow\DocumentProcessing\UpdateMode;

readonly class DocumentProcessing
{
    public const int BATCH_SIZE = 200;
    private const string SOURCE_ENTITY_TYPE = 'product';
    private const string SOURCE_CODE = 'description';

    public function __construct(
        private SourceProvider $sourceProvider,
        private DocumentUpdater $documentUpdater
    ) {
    }

    public function fullUpdate(): void
    {
        $lastProductId = 0;

        while (true) {
            $productIds = $this->sourceProvider->getProductIdsAfter(
                $lastProductId,
                self::BATCH_SIZE
            );

            if ($productIds === []) {
                return;
            }

            $this->processProducts($productIds, UpdateMode::FullUpdate);
            $lastProductId = $productIds[array_key_last($productIds)];
        }
    }

    /**
     * @param list<int> $productIds
     */
    public function deltaUpdate(array $productIds): void
    {
        foreach (array_chunk($productIds, self::BATCH_SIZE) as $productIdBatch) {
            $this->processProducts($productIdBatch, UpdateMode::DeltaUpdate);
        }
    }

    /**
     * @param list<int> $productIds
     */
    private function processProducts(array $productIds, UpdateMode $updateMode): void
    {
        $sourcesByProductId = $this->sourceProvider->getByProductIds($productIds);

        foreach ($productIds as $productId) {
            $sources = $sourcesByProductId[$productId] ?? [];

            if ($updateMode === UpdateMode::FullUpdate) {
                $this->documentUpdater->fullUpdate(
                    self::SOURCE_ENTITY_TYPE,
                    $productId,
                    self::SOURCE_CODE,
                    $sources
                );
                continue;
            }

            $this->documentUpdater->deltaUpdate(
                self::SOURCE_ENTITY_TYPE,
                $productId,
                self::SOURCE_CODE,
                $sources
            );
        }
    }
}
