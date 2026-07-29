<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\DocumentProcessing;

use DavidBel\AiSearch\Api\ChunkRepositoryInterface;
use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\DocumentRepositoryInterface;
use DavidBel\AiSearch\Model\ChunkFactory;
use DavidBel\AiSearch\Model\Chunking;
use DavidBel\AiSearch\Model\DocumentFactory;
use LogicException;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\ResourceConnection;
use Throwable;

readonly class DocumentUpdater
{
    public function __construct(
        private ResourceConnection $resourceConnection,
        private SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private SortOrderBuilder $sortOrderBuilder,
        private DocumentRepositoryInterface $documentRepository,
        private ChunkRepositoryInterface $chunkRepository,
        private DocumentFactory $documentFactory,
        private ChunkFactory $chunkFactory,
        private Chunking $chunking
    ) {
    }

    /**
     * @param list<\DavidBel\AiSearch\Workflow\DocumentProcessing\Product\ScopedSource> $sources
     */
    public function deltaUpdate(
        string $sourceEntityType,
        int $sourceEntityId,
        string $sourceCode,
        array $sources
    ): void {
        $this->update(
            $sourceEntityType,
            $sourceEntityId,
            $sourceCode,
            $sources,
            UpdateMode::DeltaUpdate
        );
    }

    /**
     * @param list<\DavidBel\AiSearch\Workflow\DocumentProcessing\Product\ScopedSource> $sources
     */
    public function fullUpdate(
        string $sourceEntityType,
        int $sourceEntityId,
        string $sourceCode,
        array $sources
    ): void {
        $this->update(
            $sourceEntityType,
            $sourceEntityId,
            $sourceCode,
            $sources,
            UpdateMode::FullUpdate
        );
    }

    /**
     * @param list<\DavidBel\AiSearch\Workflow\DocumentProcessing\Product\ScopedSource> $sources
     */
    private function update(
        string $sourceEntityType,
        int $sourceEntityId,
        string $sourceCode,
        array $sources,
        UpdateMode $updateMode
    ): void {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            $this->updateInTransaction(
                $sourceEntityType,
                $sourceEntityId,
                $sourceCode,
                $sources,
                $updateMode
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
    private function updateInTransaction(
        string $sourceEntityType,
        int $sourceEntityId,
        string $sourceCode,
        array $sources,
        UpdateMode $updateMode
    ): void {
        $documentsByStoreId = $this->getDocumentsByStoreId(
            $sourceEntityType,
            $sourceEntityId,
            $sourceCode
        );
        $currentStoreIds = [];
        $chunksBySourceHash = [];

        foreach ($sources as $source) {
            $sourceHash = hash('sha256', $source->content);
            $document = $documentsByStoreId[$source->storeId] ?? null;
            $currentStoreIds[$source->storeId] = true;

            if ($updateMode === UpdateMode::DeltaUpdate
                && $this->hasSourceHash($document, $sourceHash)
            ) {
                continue;
            }

            if (!isset($chunksBySourceHash[$sourceHash])) {
                $chunksBySourceHash[$sourceHash] = $this->chunking->chunk($source->content);
            }

            $this->updateSource(
                $document,
                $sourceEntityType,
                $sourceEntityId,
                $source->storeId,
                $sourceCode,
                $sourceHash,
                $chunksBySourceHash[$sourceHash]
            );
        }

        $this->deleteStaleDocuments($documentsByStoreId, $currentStoreIds);
    }

    private function hasSourceHash(?DocumentInterface $document, string $sourceHash): bool
    {
        return $document !== null && hash_equals($document->getSourceHash(), $sourceHash);
    }

    /**
     * @param list<string> $chunks
     */
    private function updateSource(
        ?DocumentInterface $document,
        string $sourceEntityType,
        int $sourceEntityId,
        int $storeId,
        string $sourceCode,
        string $sourceHash,
        array $chunks
    ): void {
        $persistedDocument = $document ?? $this->createDocument(
            $sourceEntityType,
            $sourceEntityId,
            $storeId,
            $sourceCode
        );
        $sourceChanged = !$this->hasSourceHash($document, $sourceHash);

        if ($sourceChanged) {
            $persistedDocument->setSourceHash($sourceHash);
            $persistedDocument = $this->documentRepository->save($persistedDocument);
        }

        $documentId = $persistedDocument->getDocumentId();

        if ($documentId === null) {
            throw new LogicException('A persisted AI search document must have an ID.');
        }

        $existingChunks = $this->getChunks($documentId);

        if ($this->getStoredChunkData($existingChunks) === $this->getGeneratedChunkData($chunks)) {
            return;
        }

        $this->replaceChunks($documentId, $existingChunks, $chunks);
    }

    private function createDocument(
        string $sourceEntityType,
        int $sourceEntityId,
        int $storeId,
        string $sourceCode
    ): DocumentInterface {
        return $this->documentFactory->create()
            ->setSourceEntityType($sourceEntityType)
            ->setSourceEntityId($sourceEntityId)
            ->setStoreId($storeId)
            ->setSourceCode($sourceCode);
    }

    /**
     * @return array<int, DocumentInterface>
     */
    private function getDocumentsByStoreId(
        string $sourceEntityType,
        int $sourceEntityId,
        string $sourceCode
    ): array {
        $searchCriteria = $this->searchCriteriaBuilderFactory->create()
            ->addFilter(DocumentInterface::SOURCE_ENTITY_TYPE, $sourceEntityType)
            ->addFilter(DocumentInterface::SOURCE_ENTITY_ID, $sourceEntityId)
            ->addFilter(DocumentInterface::SOURCE_CODE, $sourceCode)
            ->create();
        $documentsByStoreId = [];

        foreach ($this->documentRepository->getList($searchCriteria)->getItems() as $document) {
            $documentsByStoreId[$document->getStoreId()] = $document;
        }

        return $documentsByStoreId;
    }

    /**
     * @return list<ChunkInterface>
     */
    private function getChunks(int $documentId): array
    {
        $sortOrder = $this->sortOrderBuilder
            ->setField(ChunkInterface::CHUNK_INDEX)
            ->setAscendingDirection()
            ->create();
        $searchCriteria = $this->searchCriteriaBuilderFactory->create()
            ->addFilter(ChunkInterface::DOCUMENT_ID, $documentId)
            ->addSortOrder($sortOrder)
            ->create();

        return array_values($this->chunkRepository->getList($searchCriteria)->getItems());
    }

    /**
     * @param list<ChunkInterface> $chunks
     * @return list<array{string, string}>
     */
    private function getStoredChunkData(array $chunks): array
    {
        return array_map(
            static fn (ChunkInterface $chunk): array => [
                $chunk->getContentHash(),
                $chunk->getContent(),
            ],
            $chunks
        );
    }

    /**
     * @param list<string> $chunks
     * @return list<array{string, string}>
     */
    private function getGeneratedChunkData(array $chunks): array
    {
        return array_map(
            static fn (string $chunk): array => [
                hash('sha256', $chunk),
                $chunk,
            ],
            $chunks
        );
    }

    /**
     * @param list<ChunkInterface> $existingChunks
     * @param list<string> $generatedChunks
     */
    private function replaceChunks(
        int $documentId,
        array $existingChunks,
        array $generatedChunks
    ): void {
        foreach ($existingChunks as $chunk) {
            $chunkId = $chunk->getChunkId();

            if ($chunkId === null) {
                throw new LogicException('A persisted AI search chunk must have an ID.');
            }

            $this->chunkRepository->deleteById($chunkId);
        }

        foreach ($generatedChunks as $chunkIndex => $content) {
            $chunk = $this->chunkFactory->create()
                ->setDocumentId($documentId)
                ->setChunkIndex($chunkIndex)
                ->setContent($content)
                ->setContentHash(hash('sha256', $content));
            $this->chunkRepository->save($chunk);
        }
    }

    /**
     * @param array<int, DocumentInterface> $documentsByStoreId
     * @param array<int, true> $currentStoreIds
     */
    private function deleteStaleDocuments(array $documentsByStoreId, array $currentStoreIds): void
    {
        foreach ($documentsByStoreId as $storeId => $document) {
            if (isset($currentStoreIds[$storeId])) {
                continue;
            }

            $documentId = $document->getDocumentId();

            if ($documentId === null) {
                throw new LogicException('A persisted AI search document must have an ID.');
            }

            $this->documentRepository->deleteById($documentId);
        }
    }
}
