<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\DocumentRepositoryInterface;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\Chunking;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\ChunkPersistence;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\Result;
use DavidBel\AiSearch\Model\DocumentFactory;
use LogicException;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;

class DocumentUpdater
{
    public function __construct(
        private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentFactory $documentFactory,
        private readonly Parsing $parsing,
        private readonly Chunking $chunking,
        private readonly ChunkPersistence $chunkPersistence
    ) {
    }

    public function deltaUpdate(
        string $sourceEntityType,
        int $sourceEntityId,
        DocumentSource $source
    ): Result {
        return $this->update(
            $sourceEntityType,
            $sourceEntityId,
            $source,
            UpdateMode::DeltaUpdate
        );
    }

    public function fullUpdate(
        string $sourceEntityType,
        int $sourceEntityId,
        DocumentSource $source
    ): Result {
        return $this->update(
            $sourceEntityType,
            $sourceEntityId,
            $source,
            UpdateMode::FullUpdate
        );
    }

    private function update(
        string $sourceEntityType,
        int $sourceEntityId,
        DocumentSource $source,
        UpdateMode $updateMode
    ): Result {
        $documentsByStoreId = $this->getDocumentsByStoreId(
            $sourceEntityType,
            $sourceEntityId,
            $source->sourceCode
        );
        $currentStoreIds = [];
        $chunksBySourceHash = [];
        $upsertChunkIds = $deletionChunkIds = [];

        foreach ($source->storeScopedSources as $storeScopedSource) {
            $sourceHash = hash('sha256', $storeScopedSource->content);
            $document = $documentsByStoreId[$storeScopedSource->storeId] ?? null;
            $currentStoreIds[$storeScopedSource->storeId] = true;
            $sourceUnchanged = $this->hasMatchingSourceHash($document, $sourceHash);

            if ($this->isUnchangedDelta($document, $sourceHash, $storeScopedSource->title, $updateMode)) {
                continue;
            }

            if ($updateMode === UpdateMode::DeltaUpdate && $sourceUnchanged) {
                array_push(
                    $upsertChunkIds,
                    ...$this->updateDocumentTitle($document, $storeScopedSource->title)
                );

                continue;
            }

            $updateResult = $this->updateSource(
                $document,
                $sourceEntityType,
                $sourceEntityId,
                $storeScopedSource->storeId,
                $source->sourceCode,
                $storeScopedSource->title,
                $sourceHash,
                $chunksBySourceHash[$sourceHash] ??= $this->chunking->chunk(
                    $this->parsing->parse(
                        $storeScopedSource->content,
                        $source->parsingStrategy
                    )
                ),
                $updateMode
            );
            array_push($upsertChunkIds, ...$updateResult->upsertChunkIds);
            array_push($deletionChunkIds, ...$updateResult->deletionChunkIds);
        }

        array_push($deletionChunkIds, ...$this->deleteStaleDocuments($documentsByStoreId, $currentStoreIds));

        return new Result($upsertChunkIds, $deletionChunkIds);
    }

    private function isUnchangedDelta(
        ?DocumentInterface $document,
        string $sourceHash,
        ?string $title,
        UpdateMode $updateMode
    ): bool {
        return $updateMode === UpdateMode::DeltaUpdate
            && $this->hasMatchingSourceHash($document, $sourceHash)
            && $this->hasMatchingTitle($document, $title);
    }

    private function hasMatchingSourceHash(?DocumentInterface $document, string $sourceHash): bool
    {
        return $document !== null && hash_equals($document->getSourceHash(), $sourceHash);
    }

    private function hasMatchingTitle(?DocumentInterface $document, ?string $title): bool
    {
        return $document !== null && $document->getTitle() === $title;
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
        ?string $title,
        string $sourceHash,
        array $chunks,
        UpdateMode $updateMode
    ): Result {
        $persistedDocument = $document ?? $this->createDocument(
            $sourceEntityType,
            $sourceEntityId,
            $storeId,
            $sourceCode
        );
        $sourceChanged = !$this->hasMatchingSourceHash($document, $sourceHash);
        $titleChanged = !$this->hasMatchingTitle($document, $title);

        if ($sourceChanged || $titleChanged) {
            $persistedDocument->setSourceHash($sourceHash)
                ->setTitle($title);
            $persistedDocument = $this->documentRepository->save($persistedDocument);
        }

        $documentId = $persistedDocument->getDocumentId();

        if ($documentId === null) {
            throw new LogicException('A persisted AI search document must have an ID.');
        }

        $updateResult = $this->chunkPersistence->reconcile($documentId, $chunks, $updateMode);

        if (!$titleChanged || $updateMode === UpdateMode::FullUpdate) {
            return $updateResult;
        }

        return new Result(
            $this->chunkPersistence->getChunkIdsByDocumentId($documentId),
            $updateResult->deletionChunkIds
        );
    }

    /**
     * @return list<int>
     */
    private function updateDocumentTitle(?DocumentInterface $document, ?string $title): array
    {
        if ($document === null) {
            throw new LogicException('An unchanged source must have a persisted document.');
        }

        $document->setTitle($title);
        $persistedDocument = $this->documentRepository->save($document);
        $documentId = $persistedDocument->getDocumentId();

        if ($documentId === null) {
            throw new LogicException('A persisted AI search document must have an ID.');
        }

        return $this->chunkPersistence->getChunkIdsByDocumentId($documentId);
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

            array_push($deletedChunkIds, ...$this->chunkPersistence->deleteByDocumentId($documentId));
            $this->documentRepository->deleteById($documentId);
        }

        return $deletedChunkIds;
    }
}
