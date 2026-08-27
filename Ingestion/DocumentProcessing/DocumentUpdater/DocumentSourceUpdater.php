<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\DocumentRepositoryInterface;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentSource;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentSource\StoreScopedSource;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\UpdateMode;
use DavidBel\AiSearch\Model\DocumentFactory;
use LogicException;

class DocumentSourceUpdater
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentFactory $documentFactory,
        private readonly Parsing $parsing,
        private readonly Chunking $chunking,
        private readonly ChunkPersistence $chunkPersistence
    ) {
    }

    /**
     * @param array<int, DocumentInterface> $documentsByStoreId
     */
    public function update(
        string $sourceEntityType,
        int $sourceEntityId,
        DocumentSource $source,
        array $documentsByStoreId,
        UpdateMode $updateMode
    ): Result {
        $currentStoreIds = [];
        $updateResults = [];
        $chunksBySourceHash = $this->getChangedSourceChunksByHash(
            $source,
            $documentsByStoreId,
            $updateMode
        );

        foreach ($source->storeScopedSources as $storeScopedSource) {
            $updateResult = $this->updateStoreScopedSource(
                $sourceEntityType,
                $sourceEntityId,
                $source,
                $storeScopedSource,
                $documentsByStoreId[$storeScopedSource->storeId] ?? null,
                $chunksBySourceHash,
                $updateMode
            );

            if ($updateResult === null) {
                continue;
            }

            $currentStoreIds[$storeScopedSource->storeId] = true;
            $updateResults[] = $updateResult;
        }

        $updateResults[] = $this->deleteDocumentsOutsideStores(
            $documentsByStoreId,
            $currentStoreIds
        );

        return $this->combineResults($updateResults);
    }

    /**
     * @param array<int, DocumentInterface> $documentsByStoreId
     */
    public function deleteDocuments(array $documentsByStoreId): Result
    {
        return $this->deleteDocumentsOutsideStores($documentsByStoreId, []);
    }

    /**
     * @param array<int, DocumentInterface> $documentsByStoreId
     * @return array<string, list<string>>
     */
    private function getChangedSourceChunksByHash(
        DocumentSource $source,
        array $documentsByStoreId,
        UpdateMode $updateMode
    ): array {
        $chunksBySourceHash = [];

        foreach ($source->storeScopedSources as $storeScopedSource) {
            if (trim($storeScopedSource->content) === '') {
                continue;
            }

            $sourceHash = hash('sha256', $storeScopedSource->content);
            $document = $documentsByStoreId[$storeScopedSource->storeId] ?? null;

            if ($updateMode === UpdateMode::DeltaUpdate
                && $this->hasMatchingSourceHash($document, $sourceHash)
            ) {
                continue;
            }

            $chunksBySourceHash[$sourceHash] ??= $this->getChunks(
                $storeScopedSource->content,
                $source->parsingStrategy
            );
        }

        return $chunksBySourceHash;
    }

    /**
     * @param array<string, list<string>> $chunksBySourceHash
     */
    private function updateStoreScopedSource(
        string $sourceEntityType,
        int $sourceEntityId,
        DocumentSource $source,
        StoreScopedSource $storeScopedSource,
        ?DocumentInterface $document,
        array $chunksBySourceHash,
        UpdateMode $updateMode
    ): ?Result {
        if (trim($storeScopedSource->content) === '') {
            return null;
        }

        $sourceHash = hash('sha256', $storeScopedSource->content);
        $unchangedSourceResult = $this->getUnchangedSourceDeltaResult(
            $document,
            $sourceHash,
            $storeScopedSource->title,
            $updateMode
        );

        if ($unchangedSourceResult !== null) {
            return $unchangedSourceResult;
        }

        $chunks = $chunksBySourceHash[$sourceHash] ?? [];

        if ($chunks === []) {
            return null;
        }

        return $this->updateSource(
            $document,
            $sourceEntityType,
            $sourceEntityId,
            $storeScopedSource->storeId,
            $source->sourceCode,
            $storeScopedSource->title,
            $sourceHash,
            $chunks,
            $updateMode
        );
    }

    /**
     * @return list<string>
     */
    private function getChunks(string $content, string $parsingStrategy): array
    {
        return $this->chunking->chunk($this->parsing->parse($content, $parsingStrategy));
    }

    private function getUnchangedSourceDeltaResult(
        ?DocumentInterface $document,
        string $sourceHash,
        ?string $title,
        UpdateMode $updateMode
    ): ?Result {
        if ($updateMode !== UpdateMode::DeltaUpdate
            || !$this->hasMatchingSourceHash($document, $sourceHash)
        ) {
            return null;
        }

        if ($this->hasMatchingTitle($document, $title)) {
            return new Result([], []);
        }

        return new Result($this->updateDocumentTitle($document, $title), []);
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
            $updateResult->deleteChunkIds
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
     * @param array<int, DocumentInterface> $documentsByStoreId
     * @param array<int, true> $currentStoreIds
     */
    private function deleteDocumentsOutsideStores(
        array $documentsByStoreId,
        array $currentStoreIds
    ): Result {
        $deletedChunkIds = [];

        foreach ($documentsByStoreId as $storeId => $document) {
            if (isset($currentStoreIds[$storeId])) {
                continue;
            }

            $documentId = $document->getDocumentId();

            if ($documentId === null) {
                throw new LogicException('A persisted AI search document must have an ID.');
            }

            array_push(
                $deletedChunkIds,
                ...$this->chunkPersistence->deleteByDocumentId($documentId)
            );
            $this->documentRepository->deleteById($documentId);
        }

        return new Result([], $deletedChunkIds);
    }

    /**
     * @param list<Result> $results
     */
    private function combineResults(array $results): Result
    {
        $upsertChunkIds = [];
        $deleteChunkIds = [];

        foreach ($results as $result) {
            array_push($upsertChunkIds, ...$result->upsertChunkIds);
            array_push($deleteChunkIds, ...$result->deleteChunkIds);
        }

        return new Result($upsertChunkIds, $deleteChunkIds);
    }
}
