<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch\ResultProvider;

use DavidBel\AiSearch\Api\ChunkRepositoryInterface;
use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\DocumentRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;

class RelatedDocuments
{
    private const string SOURCE_ENTITY_TYPE = 'product';

    public function __construct(
        private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly ChunkRepositoryInterface $chunkRepository
    ) {
    }

    /**
     * @param list<int> $productIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function getByProductIds(array $productIds, int $storeId): array
    {
        if ($productIds === []) {
            return [];
        }

        $documents = $this->getDocuments($productIds, $storeId);
        $chunksByDocumentId = $this->getChunksByDocumentId(array_keys($documents));
        $documentIdsByProductId = $this->getDocumentIdsByProductId($documents);

        return $this->getDocumentsByProductId(
            $documentIdsByProductId,
            $documents,
            $chunksByDocumentId
        );
    }

    /**
     * @param list<int> $productIds
     * @return array<int, array{
     *     id: int,
     *     product_id: int,
     *     store_id: int,
     *     source_code: string,
     *     title: string|null,
     *     created_at: string|null,
     *     updated_at: string|null
     * }>
     */
    private function getDocuments(array $productIds, int $storeId): array
    {
        $searchCriteria = $this->searchCriteriaBuilderFactory->create()
            ->addFilter(DocumentInterface::SOURCE_ENTITY_TYPE, self::SOURCE_ENTITY_TYPE)
            ->addFilter(DocumentInterface::SOURCE_ENTITY_ID, $productIds, 'in')
            ->addFilter(DocumentInterface::STORE_ID, $storeId)
            ->create();
        $documents = [];

        foreach ($this->documentRepository->getList($searchCriteria)->getItems() as $document) {
            $documentId = (int) $document->getDocumentId();

            $documents[$documentId] = [
                'id' => $documentId,
                'product_id' => $document->getSourceEntityId(),
                'store_id' => $document->getStoreId(),
                'source_code' => $document->getSourceCode(),
                'title' => $document->getTitle(),
                'created_at' => $document->getCreatedAt(),
                'updated_at' => $document->getUpdatedAt(),
            ];
        }

        return $documents;
    }

    /**
     * @param list<int> $documentIds
     * @return array<int, array<int, array{
     *     id: int,
     *     index: int,
     *     content: string,
     *     created_at: string|null,
     *     updated_at: string|null
     * }>>
     */
    private function getChunksByDocumentId(array $documentIds): array
    {
        if ($documentIds === []) {
            return [];
        }

        $searchCriteria = $this->searchCriteriaBuilderFactory->create()
            ->addFilter(ChunkInterface::DOCUMENT_ID, $documentIds, 'in')
            ->create();
        $chunksByDocumentId = [];

        foreach ($this->chunkRepository->getList($searchCriteria)->getItems() as $chunk) {
            $chunkId = (int) $chunk->getChunkId();

            $chunksByDocumentId[$chunk->getDocumentId()][$chunk->getChunkIndex()] = [
                'id' => $chunkId,
                'index' => $chunk->getChunkIndex(),
                'content' => $chunk->getContent(),
                'created_at' => $chunk->getCreatedAt(),
                'updated_at' => $chunk->getUpdatedAt(),
            ];
        }

        foreach ($chunksByDocumentId as $documentId => $chunks) {
            ksort($chunks);
            $chunksByDocumentId[$documentId] = $chunks;
        }

        return $chunksByDocumentId;
    }

    /**
     * @param array<int, array{product_id: int, source_code: string}> $documents
     * @return array<int, array<string, int>>
     */
    private function getDocumentIdsByProductId(array $documents): array
    {
        $documentIdsByProductId = [];

        foreach ($documents as $documentId => $document) {
            $documentIdsByProductId[$document['product_id']][$document['source_code']] = $documentId;
        }

        foreach ($documentIdsByProductId as $productId => $documentIdsBySourceCode) {
            ksort($documentIdsBySourceCode);
            $documentIdsByProductId[$productId] = $documentIdsBySourceCode;
        }

        return $documentIdsByProductId;
    }

    /**
     * @param array<int, array<string, int>> $documentIdsByProductId
     * @param array<int, array{
     *     id: int,
     *     product_id: int,
     *     store_id: int,
     *     source_code: string,
     *     title: string|null,
     *     created_at: string|null,
     *     updated_at: string|null
     * }> $documents
     * @param array<int, array<int, array{
     *     id: int,
     *     index: int,
     *     content: string,
     *     created_at: string|null,
     *     updated_at: string|null
     * }>> $chunksByDocumentId
     * @return array<int, list<array<string, mixed>>>
     */
    private function getDocumentsByProductId(
        array $documentIdsByProductId,
        array $documents,
        array $chunksByDocumentId
    ): array {
        $documentsByProductId = [];

        foreach ($documentIdsByProductId as $productId => $documentIdsBySourceCode) {
            foreach ($documentIdsBySourceCode as $documentId) {
                $document = $documents[$documentId];
                unset($document['product_id']);
                $document['chunks'] = array_values($chunksByDocumentId[$documentId] ?? []);
                $documentsByProductId[$productId][] = $document;
            }
        }

        return $documentsByProductId;
    }
}
