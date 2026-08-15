<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress\Support;

use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Config\SearchConfig;
use DavidBel\AiSearch\Indexer\ProductIndexer;
use DavidBel\AiSearch\Indexer\Versioning\IndexName;
use DavidBel\AiSearch\Model\ResourceModel\Document\CollectionFactory as DocumentCollectionFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory as BacklogCollectionFactory;
use Magento\Elasticsearch\SearchAdapter\ConnectionManager;
use Magento\Framework\FlagManager;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\OpenSearch\Model\SearchClient;
use RuntimeException;

class EnvironmentReset
{
    private const string FLAG_CODE = 'davidbel_ai_search_index_version';

    public function __construct(
        private readonly BacklogCollectionFactory $backlogCollectionFactory,
        private readonly DocumentCollectionFactory $documentCollectionFactory,
        private readonly FlagManager $flagManager,
        private readonly ConnectionManager $connectionManager,
        private readonly IndexName $indexName,
        private readonly SearchConfig $searchConfig,
        private readonly OpenSearch $openSearch,
        private readonly IndexerRegistry $indexerRegistry
    ) {
    }

    /**
     * @return array{removed_indexes: int, removed_documents: int, removed_chunks: int, removed_backlog: int}
     */
    public function execute(): array
    {
        $counts = $this->getLocalCounts();
        $removedIndexes = $this->removeModuleIndexes();
        $this->removeLocalData();
        $this->flagManager->deleteFlag(self::FLAG_CODE);
        $this->indexerRegistry->get(ProductIndexer::ID)->invalidate();

        return [
            'removed_indexes' => $removedIndexes,
            'removed_documents' => $counts['documents'],
            'removed_chunks' => $counts['chunks'],
            'removed_backlog' => $counts['backlog'],
        ];
    }

    /**
     * @return array{documents: int, chunks: int, backlog: int}
     */
    private function getLocalCounts(): array
    {
        $documentCollection = $this->documentCollectionFactory->create();
        $chunkTable = $documentCollection->getConnection()->getTableName('davidbel_ai_search_chunk');

        return [
            'documents' => $documentCollection->getSize(),
            'chunks' => (int) $documentCollection->getConnection()->fetchOne(
                $documentCollection->getConnection()->select()->from($chunkTable, 'COUNT(*)')
            ),
            'backlog' => $this->backlogCollectionFactory->create()->getSize(),
        ];
    }

    private function removeLocalData(): void
    {
        $backlogResource = $this->backlogCollectionFactory->create()->getResourceModel();
        /** @var \Magento\Framework\DB\Adapter\AdapterInterface $connection */
        $connection = $backlogResource->getConnection();
        $connection->delete($backlogResource->getMainTable());
        $documentResource = $this->documentCollectionFactory->create()->getResourceModel();
        /** @var \Magento\Framework\DB\Adapter\AdapterInterface $connection */
        $connection = $documentResource->getConnection();
        $connection->delete($documentResource->getMainTable());
    }

    private function removeModuleIndexes(): int
    {
        $connection = $this->connectionManager->getConnection();

        if (!$connection instanceof SearchClient) {
            throw new RuntimeException('Magento is not configured to use OpenSearch.');
        }

        $indexes = $connection->getOpenSearchClient()->indices()->get(['index' => '*']);
        $removed = 0;

        foreach (array_keys($indexes) as $index) {
            if (!is_string($index) || !$this->isModuleIndex($index)) {
                continue;
            }

            $this->openSearch->deleteIndex($index);
            $removed++;
        }

        return $removed;
    }

    private function isModuleIndex(string $index): bool
    {
        foreach ([$this->indexName->getAlias(), $this->searchConfig->getIndexName()] as $root) {
            if ($index === $root || preg_match('/^' . preg_quote($root, '/') . '_v[1-9][0-9]*$/', $index) === 1) {
                return true;
            }
        }

        return false;
    }
}
