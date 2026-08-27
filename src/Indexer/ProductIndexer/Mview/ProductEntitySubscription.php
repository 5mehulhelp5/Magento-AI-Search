<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\ProductIndexer\Mview;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\TriggerFactory;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Mview\Config;
use Magento\Framework\Mview\View\CollectionInterface;
use Magento\Framework\Mview\View\Subscription;
use Magento\Framework\Mview\View\SubscriptionStatementPostprocessorInterface;
use Magento\Framework\Mview\ViewInterface;

class ProductEntitySubscription extends Subscription
{
    private const string PRODUCT_ATTRIBUTE_TABLE_PREFIX = 'catalog_product_entity_';

    /**
     * @param list<string> $ignoredUpdateColumns
     * @param array<string, array<string, list<string>>> $ignoredUpdateColumnsBySubscription
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        ResourceConnection $resource,
        TriggerFactory $triggerFactory,
        CollectionInterface $viewCollection,
        ViewInterface $view,
        private readonly MetadataPool $metadataPool,
        string $tableName,
        string $columnName,
        array $ignoredUpdateColumns = [],
        array $ignoredUpdateColumnsBySubscription = [],
        ?Config $mviewConfig = null,
        ?SubscriptionStatementPostprocessorInterface $statementPostprocessor = null
    ) {
        parent::__construct(
            $resource,
            $triggerFactory,
            $viewCollection,
            $view,
            $tableName,
            $columnName,
            $ignoredUpdateColumns,
            $ignoredUpdateColumnsBySubscription,
            $mviewConfig,
            $statementPostprocessor
        );
    }

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function getEntityColumn(string $prefix, ViewInterface $view): string
    {
        $productMetadata = $this->metadataPool->getMetadata(ProductInterface::class);
        $productIdentifierField = $productMetadata->getIdentifierField();
        $productLinkField = $productMetadata->getLinkField();
        $sourceProductLinkColumn = $this->getSourceProductLinkColumn($productLinkField);

        if ($productIdentifierField === $productLinkField) {
            return $prefix . $this->connection->quoteIdentifier($sourceProductLinkColumn);
        }

        return sprintf(
            '(SELECT %s FROM %s WHERE %s = %s LIMIT 1)',
            $this->connection->quoteIdentifier($productIdentifierField),
            $this->connection->quoteIdentifier($productMetadata->getEntityTable()),
            $this->connection->quoteIdentifier($productLinkField),
            $prefix . $this->connection->quoteIdentifier($sourceProductLinkColumn)
        );
    }

    private function getSourceProductLinkColumn(string $productLinkField): string
    {
        if (str_starts_with($this->getTableName(), self::PRODUCT_ATTRIBUTE_TABLE_PREFIX)) {
            return $productLinkField;
        }

        return $this->getColumnName();
    }
}
