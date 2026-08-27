<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater;

use DavidBel\AiSearch\Api\ChunkRepositoryInterface;
use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\UpdateMode;
use DavidBel\AiSearch\Model\ChunkFactory;
use LogicException;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SortOrderBuilder;

class ChunkPersistence
{
    public function __construct(
        private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly ChunkRepositoryInterface $chunkRepository,
        private readonly ChunkFactory $chunkFactory
    ) {
    }

    /**
     * @param list<string> $generatedChunks
     */
    public function reconcile(
        int $documentId,
        array $generatedChunks,
        UpdateMode $updateMode
    ): Result {
        $existingChunksByIndex = $this->getChunksByIndex($this->getChunks($documentId));
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

        return new Result(
            $upsertChunkIds,
            $this->deleteChunks($existingChunksByIndex)
        );
    }

    /**
     * @return list<int>
     */
    public function getChunkIdsByDocumentId(int $documentId): array
    {
        $chunkIds = [];

        foreach ($this->getChunks($documentId) as $chunk) {
            $chunkIds[] = $this->getPersistedChunkId($chunk);
        }

        return $chunkIds;
    }

    /**
     * @return list<int>
     */
    public function deleteByDocumentId(int $documentId): array
    {
        return $this->deleteChunks($this->getChunks($documentId));
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
}
