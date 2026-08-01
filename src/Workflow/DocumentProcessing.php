<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory
    as EmbeddingBacklogCollectionFactory;
use DavidBel\AiSearch\Workflow\DocumentProcessing\DocumentUpdateResult;
use DavidBel\AiSearch\Workflow\DocumentProcessing\DocumentUpdater;
use DavidBel\AiSearch\Workflow\DocumentProcessing\Product\SourceProvider;
use DavidBel\AiSearch\Workflow\DocumentProcessing\UpdateMode;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Throwable;

class DocumentProcessing
{
    public const int BATCH_SIZE = 200;
    private const string SOURCE_ENTITY_TYPE = 'product';
    private const string SOURCE_CODE = 'description';

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
        $embeddingBacklogResource = $this->embeddingBacklogCollectionFactory
            ->create()
            ->getResourceModel();
        /** @var AdapterInterface $connection */
        $connection = $embeddingBacklogResource->getConnection();

        foreach ($productIds as $productId) {
            $sources = $sourcesByProductId[$productId] ?? [];
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
     * @param list<\DavidBel\AiSearch\Workflow\DocumentProcessing\Product\ScopedSource> $sources
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
            $updateResult = $this->updateProduct($productId, $sources, $updateMode);
            $this->saveBacklog(
                $updateResult,
                $embeddingBacklogResource,
                $productId
            );
            $connection->commit();
        } catch (Throwable $throwable) {
            $connection->rollBack();
            throw $throwable;
        }
    }

    /**
     * @param list<\DavidBel\AiSearch\Workflow\DocumentProcessing\Product\ScopedSource> $sources
     */
    private function updateProduct(
        int $productId,
        array $sources,
        UpdateMode $updateMode
    ): DocumentUpdateResult {
        if ($updateMode === UpdateMode::FullUpdate) {
            return $this->documentUpdater->fullUpdate(
                self::SOURCE_ENTITY_TYPE,
                $productId,
                self::SOURCE_CODE,
                $sources
            );
        }

        return $this->documentUpdater->deltaUpdate(
            self::SOURCE_ENTITY_TYPE,
            $productId,
            self::SOURCE_CODE,
            $sources
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
