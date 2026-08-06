<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory
    as EmbeddingBacklogCollectionFactory;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdateResult;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\ProductSource;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\UpdateMode;
use Magento\Framework\DB\Adapter\AdapterInterface;
use RuntimeException;
use Throwable;

class DocumentProcessing
{
    public const int BATCH_SIZE = 200;
    private const string SOURCE_ENTITY_TYPE = 'product';

    public function __construct(
        private readonly SourceProvider $sourceProvider,
        private readonly DocumentUpdater $documentUpdater,
        private readonly EmbeddingBacklogCollectionFactory $embeddingBacklogCollectionFactory
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
        $affectedProductIds = $this->getAffectedProductIds($productIds);

        foreach (array_chunk($affectedProductIds, self::BATCH_SIZE) as $productIdBatch) {
            $this->processProducts($productIdBatch, UpdateMode::DeltaUpdate);
        }
    }

    /**
     * @param list<int> $productIds
     * @return list<int>
     */
    private function getAffectedProductIds(array $productIds): array
    {
        $affectedProductIds = [];

        foreach (array_chunk($productIds, self::BATCH_SIZE) as $productIdBatch) {
            $affectedProductIds += array_fill_keys(
                $this->sourceProvider->getAffectedProductIds($productIdBatch),
                true
            );
        }

        return array_keys($affectedProductIds);
    }

    /**
     * @param list<int> $productIds
     */
    private function processProducts(array $productIds, UpdateMode $updateMode): void
    {
        $sourcesByProductId = $this->sourceProvider->getSourcesByProductIds($productIds);
        $embeddingBacklogResource = $this->embeddingBacklogCollectionFactory
            ->create()
            ->getResourceModel();
        /** @var AdapterInterface $connection */
        $connection = $embeddingBacklogResource->getConnection();

        foreach ($productIds as $productId) {
            $sources = $sourcesByProductId[$productId] ?? null;

            if ($sources === null) {
                throw new RuntimeException('Product sources could not be resolved.');
            }

            $this->processProduct(
                $productId,
                $sources,
                $updateMode,
                $embeddingBacklogResource,
                $connection
            );
        }
    }

    /**
     * @param list<ProductSource> $sources
     */
    private function processProduct(
        int $productId,
        array $sources,
        UpdateMode $updateMode,
        EmbeddingBacklogResource $embeddingBacklogResource,
        AdapterInterface $connection
    ): void {
        $connection->beginTransaction();

        try {
            foreach ($sources as $source) {
                $updateResult = $this->updateProductSource($productId, $source, $updateMode);
                $this->saveBacklog(
                    $updateResult,
                    $embeddingBacklogResource,
                    $productId
                );
            }

            $connection->commit();
        } catch (Throwable $throwable) {
            $connection->rollBack();
            throw $throwable;
        }
    }

    private function updateProductSource(
        int $productId,
        ProductSource $source,
        UpdateMode $updateMode
    ): DocumentUpdateResult {
        if ($updateMode === UpdateMode::FullUpdate) {
            return $this->documentUpdater->fullUpdate(
                self::SOURCE_ENTITY_TYPE,
                $productId,
                $source->sourceCode,
                $source->scopedSources
            );
        }

        return $this->documentUpdater->deltaUpdate(
            self::SOURCE_ENTITY_TYPE,
            $productId,
            $source->sourceCode,
            $source->scopedSources
        );
    }

    private function saveBacklog(
        DocumentUpdateResult $updateResult,
        EmbeddingBacklogResource $embeddingBacklogResource,
        int $productId
    ): void {
        if ($updateResult->upsertChunkIds === [] && $updateResult->deletionChunkIds === []) {
            return;
        }

        foreach ($updateResult->upsertChunkIds as $chunkId) {
            $embeddingBacklogResource->saveByChunkId(
                $chunkId,
                self::SOURCE_ENTITY_TYPE,
                $productId
            );
        }

        foreach ($updateResult->deletionChunkIds as $chunkId) {
            $embeddingBacklogResource->deleteByChunkId(
                $chunkId,
                self::SOURCE_ENTITY_TYPE,
                $productId
            );
        }
    }
}
