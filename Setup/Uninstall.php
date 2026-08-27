<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Setup;

use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Indexer\ProductAttributeOptionIndexer;
use DavidBel\AiSearch\Indexer\ProductIndexer;
use DavidBel\AiSearch\Indexer\Versioning\State\Flag;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    /**
     * @var list<string>
     */
    private const array INDEXER_IDS = [
        ProductAttributeOptionIndexer::ID,
        ProductIndexer::ID,
    ];

    /**
     * @var list<string>
     */
    private const array MVIEW_IDS = [
        'davidbel_ai_search_product_attribute_option_mview',
        ProductIndexer::VIEW_ID,
    ];

    /**
     * @var list<string>
     */
    private const array CRON_JOB_CODES = [
        'davidbel_ai_search_chunk_processing',
        'davidbel_ai_search_chunk_processing_retry',
        'davidbel_ai_search_chunk_processing_cleanup',
        'davidbel_ai_search_chunk_delete',
    ];

    /**
     * @var list<string>
     */
    private const array MODULE_TABLES = [
        'davidbel_ai_search_embedding_backlog',
        'davidbel_ai_search_chunk',
        'davidbel_ai_search_document',
    ];

    private const string ACL_RESOURCE_PATTERN = '^DavidBel_AiSearch::';
    private const string CONFIG_PATH_PATTERN = '^davidbel_ai_search_';
    private const string UI_BOOKMARK_NAMESPACE_PATTERN = '^davidbel_ai_search_';
    private const string VERSION_STATE_FLAG = 'davidbel_ai_search_index_version';

    public function __construct(
        private readonly OpenSearch $openSearch,
        private readonly Flag $stateFlag,
        private readonly IndexerRegistry $indexerRegistry
    ) {
    }

    public function uninstall(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ): void {
        $this->deleteOpenSearchIndexes();
        $this->unsubscribeIndexers();

        $setup->startSetup();

        try {
            $this->removeDatabaseData($setup);
        } finally {
            $setup->endSetup();
        }
    }

    private function deleteOpenSearchIndexes(): void
    {
        foreach ($this->getOpenSearchIndexNames() as $indexName) {
            $this->openSearch->deleteIndex($indexName);
        }
    }

    /**
     * @return list<string>
     */
    private function getOpenSearchIndexNames(): array
    {
        $indexNames = array_fill_keys($this->openSearch->getVersionIndexNames(), true);
        $state = $this->stateFlag->get();

        if ($state->active !== null) {
            $indexNames[$state->active->indexName] = true;
        }

        if ($state->target !== null) {
            $indexNames[$state->target->physicalIndex->indexName] = true;
        }

        return array_keys($indexNames);
    }

    private function unsubscribeIndexers(): void
    {
        foreach (self::INDEXER_IDS as $indexerId) {
            $this->indexerRegistry->get($indexerId)->setScheduled(false);
        }
    }

    private function removeDatabaseData(SchemaSetupInterface $setup): void
    {
        $connection = $setup->getConnection();

        $this->dropModuleTables($setup, $connection);
        $this->deleteModuleState($setup, $connection);
    }

    private function dropModuleTables(
        SchemaSetupInterface $setup,
        AdapterInterface $connection
    ): void {
        foreach (self::MODULE_TABLES as $tableName) {
            $connection->dropTable($setup->getTable($tableName));
        }
    }

    private function deleteModuleState(
        SchemaSetupInterface $setup,
        AdapterInterface $connection
    ): void {
        $connection->delete(
            $setup->getTable('indexer_state'),
            ['indexer_id IN (?)' => self::INDEXER_IDS]
        );
        $connection->delete(
            $setup->getTable('mview_state'),
            ['view_id IN (?)' => self::MVIEW_IDS]
        );
        $connection->delete(
            $setup->getTable('flag'),
            ['flag_code = ?' => self::VERSION_STATE_FLAG]
        );
        $connection->delete(
            $setup->getTable('core_config_data'),
            ['path REGEXP ?' => self::CONFIG_PATH_PATTERN]
        );
        $connection->delete(
            $setup->getTable('cron_schedule'),
            ['job_code IN (?)' => self::CRON_JOB_CODES]
        );
        $connection->delete(
            $setup->getTable('ui_bookmark'),
            ['namespace REGEXP ?' => self::UI_BOOKMARK_NAMESPACE_PATTERN]
        );
        $connection->delete(
            $setup->getTable('authorization_rule'),
            ['resource_id REGEXP ?' => self::ACL_RESOURCE_PATTERN]
        );
    }
}
