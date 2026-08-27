<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Model\ResourceModel\Document\CollectionFactory
    as DocumentCollectionFactory;
use InvalidArgumentException;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
    as ProductCollectionFactory;
use RuntimeException;

class DeletedProductIdProvider
{
    private const string SOURCE_ENTITY_TYPE = 'product';

    public function __construct(
        private readonly DocumentCollectionFactory $documentCollectionFactory,
        private readonly ProductCollectionFactory $productCollectionFactory
    ) {
    }

    /**
     * @return list<int>
     */
    public function getProductIdsFrom(int $fromProductId, int $limit): array
    {
        if ($fromProductId < 0) {
            throw new InvalidArgumentException('The starting product ID cannot be negative.');
        }

        if ($limit < 1) {
            throw new InvalidArgumentException('The product batch limit must be positive.');
        }

        $documentResource = $this->documentCollectionFactory->create()->getResourceModel();
        $productResource = $this->createProductResource();
        /** @var \Magento\Framework\DB\Adapter\AdapterInterface $connection */
        $connection = $documentResource->getConnection();
        $sourceEntityId = DocumentInterface::SOURCE_ENTITY_ID;
        $select = $connection->select()
            ->distinct()
            ->from(
                ['document' => $documentResource->getMainTable()],
                [$sourceEntityId]
            )
            ->joinLeft(
                ['product' => $productResource->getEntityTable()],
                sprintf('product.entity_id = document.%s', $sourceEntityId),
                []
            )
            ->where('document.' . DocumentInterface::SOURCE_ENTITY_TYPE . ' = ?', self::SOURCE_ENTITY_TYPE)
            ->where('document.' . $sourceEntityId . ' > ?', $fromProductId)
            ->where('product.entity_id IS NULL')
            ->order('document.' . $sourceEntityId . ' ASC')
            ->limit($limit);

        return $this->toProductIds($connection->fetchCol($select));
    }

    private function createProductResource(): ProductResource
    {
        /** @var ProductResource $productResource */
        $productResource = $this->productCollectionFactory->create()->getEntity();

        return $productResource;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return list<int>
     */
    private function toProductIds(array $values): array
    {
        $productIds = [];

        foreach ($values as $value) {
            $productId = filter_var($value, FILTER_VALIDATE_INT);

            if (!is_int($productId) || $productId < 1) {
                throw new RuntimeException(
                    'The deleted product query returned an invalid product ID.'
                );
            }

            $productIds[] = $productId;
        }

        return $productIds;
    }
}
