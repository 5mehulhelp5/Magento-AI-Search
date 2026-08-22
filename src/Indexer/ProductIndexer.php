<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer;

use DavidBel\AiSearch\Ingestion\DocumentProcessing;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\UpdateMode;
use DavidBel\AiSearch\Log\Logger;
use InvalidArgumentException;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Throwable;

class ProductIndexer implements IndexerActionInterface, MviewActionInterface
{
    public const string ID = 'davidbel_ai_search_product_indexer';

    public function __construct(
        private readonly DocumentProcessing $documentProcessing,
        private readonly Versioning $versioning,
        private readonly Logger $logger
    ) {
    }

    public function executeFull(): void
    {
        $updateMode = UpdateMode::FullUpdate;
        $this->logger->indexerStarted(self::ID, $updateMode->value);

        try {
            $this->versioning->prepareTargetForFullReindex();
            $this->documentProcessing->fullUpdate(
                $this->versioning->getTargetIndexVersion()
            );
            $this->versioning->markTargetDocumentProcessingComplete();
        } catch (Throwable $throwable) {
            $this->logger->indexerFailed(
                self::ID,
                $updateMode->value,
                $throwable
            );
            throw $throwable;
        }

        $this->logger->indexerCompleted(self::ID, $updateMode->value);
    }

    /**
     * @param array<array-key, mixed> $ids
     */
    public function executeList(array $ids): void
    {
        $updateMode = UpdateMode::DeltaUpdate;
        $this->logger->indexerStarted(self::ID, $updateMode->value);

        try {
            $this->executeDeltaUpdate($ids);
        } catch (Throwable $throwable) {
            $this->logger->indexerFailed(
                self::ID,
                $updateMode->value,
                $throwable
            );
            throw $throwable;
        }

        $this->logger->indexerCompleted(self::ID, $updateMode->value);
    }

    /**
     * @param array<array-key, mixed> $ids
     */
    private function executeDeltaUpdate(array $ids): void
    {
        if (!$this->versioning->hasTargetOrActiveForCurrentConfiguration()) {
            $this->versioning->invalidateProductIndexerWhenNeeded();

            return;
        }

        $this->documentProcessing->deltaUpdate(
            $this->normalizeIds($ids),
            $this->versioning->getIngestionIndexVersion()
        );
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
    private function normalizeIds(array $ids): array
    {
        $normalizedIds = [];

        foreach ($ids as $id) {
            $productId = filter_var($id, FILTER_VALIDATE_INT);

            if ($productId === false || $productId < 1) {
                throw new InvalidArgumentException('Product IDs must be positive integers.');
            }

            $normalizedIds[$productId] = $productId;
        }

        return array_values($normalizedIds);
    }
}
