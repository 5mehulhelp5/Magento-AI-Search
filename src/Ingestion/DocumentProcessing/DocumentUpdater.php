<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing;

use DavidBel\AiSearch\Api\ChunkRepositoryInterface;
use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\DocumentRepositoryInterface;
use DavidBel\AiSearch\Model\ChunkFactory;
use DavidBel\AiSearch\Model\DocumentFactory;
use LogicException;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SortOrderBuilder;

class DocumentUpdater
{
    public function __construct(
        private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly ChunkRepositoryInterface $chunkRepository,
        private readonly DocumentFactory $documentFactory,
        private readonly ChunkFactory $chunkFactory,
        private readonly Chunking $chunking
    ) {
    }

    /**
     * @param list<\DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\ScopedSource> $sources
     */
    public function deltaUpdate(
        string $sourceEntityType,
        int $sourceEntityId,
        string $sourceCode,
        array $sources
    ): DocumentUpdateResult {
        return $this->update(
            $sourceEntityType,
            $sourceEntityId,
            $sourceCode,
            $sources,
            UpdateMode::DeltaUpdate
        );
    }

    /**
     * @param list<\DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\ScopedSource> $sources
     */
    public function fullUpdate(
        string $sourceEntityType,
        int $sourceEntityId,
        string $sourceCode,
        array $sources
    ): DocumentUpdateResult {
        return $this->update(
            $sourceEntityType,
            $sourceEntityId,
            $sourceCode,
            $sources,
            UpdateMode::FullUpdate
        );
    }

    /**
     * @param list<\DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\ScopedSource> $sources
     */
    private function update(
        string $sourceEntityType,
        int $sourceEntityId,
        string $sourceCode,
        array $sources,
        UpdateMode $updateMode
    ): DocumentUpdateResult {
        $documentsByStoreId = $this->getDocumentsByStoreId(
            $sourceEntityType,
            $sourceEntityId,
            $sourceCode
        );
        $currentStoreIds = [];
        $chunksBySourceHash = [];
        $upsertChunkIds = $deletionChunkIds = [];

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

            $updateResult = $this->updateSource(
                $document,
                $sourceEntityType,
                $sourceEntityId,
                $source->storeId,
                $sourceCode,
                $sourceHash,
                $chunksBySourceHash[$sourceHash],
                $updateMode
            );
            array_push($upsertChunkIds, ...$updateResult->upsertChunkIds);
            array_push($deletionChunkIds, ...$updateResult->deletionChunkIds);
        }

        array_push($deletionChunkIds, ...$this->deleteStaleDocuments($documentsByStoreId, $currentStoreIds));

        return new DocumentUpdateResult($upsertChunkIds, $deletionChunkIds);
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
        array $chunks,
        UpdateMode $updateMode
    ): DocumentUpdateResult {
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

        return $this->updateChunks($documentId, $existingChunks, $chunks, $updateMode);
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
     * @param list<ChunkInterface> $existingChunks
     * @param list<string> $generatedChunks
     */
    private function updateChunks(
        int $documentId,
        array $existingChunks,
        array $generatedChunks,
        UpdateMode $updateMode
    ): DocumentUpdateResult {
        $existingChunksByIndex = $this->getChunksByIndex($existingChunks);
        $upsertChunkIds = [];

        foreach ($generatedChunks as $chunkIndex => $content) {
            $existingChunk = $existingChunksByIndex[$chunkIndex] ?? null;
            unset($existingChunksByIndex[$chunkIndex]);
            $contentHash = hash('sha256', $content);

            if ($existingChunk !== null
                && $this->hasChunkContent($existingChunk, $content, $contentHash)
            ) {
                if ($updateMode === UpdateMode::FullUpdate) {
                    $upsertChunkIds[] = $this->getPersistedChunkId($existingChunk);
                }

                continue;
            }

            $chunk = $existingChunk ?? $this->createChunk($documentId, $chunkIndex);
            $chunk->setContent($content)
                ->setContentHash($contentHash);
            $upsertChunkIds[] = $this->getPersistedChunkId(
                $this->chunkRepository->save($chunk)
            );
        }

        return new DocumentUpdateResult(
            $upsertChunkIds,
            $this->deleteChunks($existingChunksByIndex)
        );
    }

    /**
     * @param list<ChunkInterface> $chunks
     * @return array<int, ChunkInterface>
     */
    private function getChunksByIndex(array $chunks): array
    {
        $chunksByIndex = [];

        foreach ($chunks as $chunk) {
            $chunksByIndex[$chunk->getChunkIndex()] = $chunk;
        }

        return $chunksByIndex;
    }

    private function hasChunkContent(
        ChunkInterface $chunk,
        string $content,
        string $contentHash
    ): bool {
        return hash_equals($chunk->getContentHash(), $contentHash)
            && $chunk->getContent() === $content;
    }

    private function createChunk(int $documentId, int $chunkIndex): ChunkInterface
    {
        return $this->chunkFactory->create()
            ->setDocumentId($documentId)
            ->setChunkIndex($chunkIndex);
    }

    private function getPersistedChunkId(ChunkInterface $chunk): int
    {
        $chunkId = $chunk->getChunkId();

        if ($chunkId === null) {
            throw new LogicException('A persisted AI search chunk must have an ID.');
        }

        return $chunkId;
    }

    /**
     * @param array<int, ChunkInterface> $chunks
     * @return list<int>
     */
    private function deleteChunks(array $chunks): array
    {
        $deletedChunkIds = [];

        foreach ($chunks as $chunk) {
            $chunkId = $this->getPersistedChunkId($chunk);
            $this->chunkRepository->deleteById($chunkId);
            $deletedChunkIds[] = $chunkId;
        }

        return $deletedChunkIds;
    }

    /**
     * @param array<int, DocumentInterface> $documentsByStoreId
     * @param array<int, true> $currentStoreIds
     * @return list<int>
     */
    private function deleteStaleDocuments(array $documentsByStoreId, array $currentStoreIds): array
    {
        $deletedChunkIds = [];

        foreach ($documentsByStoreId as $storeId => $document) {
            if (isset($currentStoreIds[$storeId])) {
                continue;
            }

            $documentId = $document->getDocumentId();

            if ($documentId === null) {
                throw new LogicException('A persisted AI search document must have an ID.');
            }

            array_push($deletedChunkIds, ...$this->deleteChunks($this->getChunks($documentId)));
            $this->documentRepository->deleteById($documentId);
        }

        return $deletedChunkIds;
    }
}
