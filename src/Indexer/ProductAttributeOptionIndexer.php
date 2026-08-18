<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer;

use DavidBel\AiSearch\Config\DataProcessingConfig;
use DavidBel\AiSearch\Indexer\ProductAttributeOptionIndexer\AffectedProductIdProvider;
use DavidBel\AiSearch\Indexer\ProductAttributeOptionIndexer\ProductIndexerPublisher;
use InvalidArgumentException;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;

class ProductAttributeOptionIndexer implements IndexerActionInterface, MviewActionInterface
{
    public const string ID = 'davidbel_ai_search_product_attribute_option_indexer';

    public function __construct(
        private readonly AffectedProductIdProvider $affectedProductIdProvider,
        private readonly ProductIndexerPublisher $productIndexerPublisher,
        private readonly DataProcessingConfig $dataProcessingConfig
    ) {
    }

    public function executeFull(): void
    {
        $this->productIndexerPublisher->invalidateProductIndexer();
    }

    /**
     * @param array<array-key, mixed> $ids
     */
    public function executeList(array $ids): void
    {
        $optionIds = $this->normalizeOptionIds($ids);
        $batchSize = $this->dataProcessingConfig->getDocumentProcessingBatchSize();
        $productIdBatches = $this->affectedProductIdProvider->getProductIdBatches(
            $optionIds,
            $batchSize
        );

        foreach ($productIdBatches as $productIds) {
            $this->productIndexerPublisher->publishProductIds($productIds);
        }
    }

    public function executeRow(mixed $id): void
    {
        $this->executeList([$id]);
    }

    public function execute(mixed $ids): void
    {
        $this->executeList($ids);
    }

    /**
     * @param array<array-key, mixed> $ids
     * @return list<int>
     */
    private function normalizeOptionIds(array $ids): array
    {
        $optionIds = [];

        foreach ($ids as $id) {
            $optionId = filter_var($id, FILTER_VALIDATE_INT);

            if ($optionId === false || $optionId < 1) {
                throw new InvalidArgumentException('Attribute option IDs must be positive integers.');
            }

            $optionIds[$optionId] = $optionId;
        }

        return array_values($optionIds);
    }
}
