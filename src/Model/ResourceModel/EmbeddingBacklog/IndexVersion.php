<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklog\FullReindexStatus;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use InvalidArgumentException;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Sql\Expression;
use UnexpectedValueException;

class IndexVersion
{
    private ?EmbeddingBacklogResource $resource = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function getHighestIndexVersion(): ?int
    {
        $resource = $this->getResource();
        /** @var AdapterInterface $connection */
        $connection = $resource->getConnection();
        $select = $connection->select()
            ->from(
                $resource->getMainTable(),
                [new Expression(sprintf('MAX(%s)', EmbeddingBacklogInterface::INDEX_VERSION))]
            );
        $value = (int) $connection->fetchOne($select);

        if ($value < 1) {
            return null;
        }

        return $value;
    }

    /**
     * @param array<int, int> $backlogIndexVersions
     */
    public function markFullReindexItemsIndexed(array $backlogIndexVersions): void
    {
        if ($backlogIndexVersions === []) {
            return;
        }

        $resource = $this->getResource();
        /** @var AdapterInterface $connection */
        $connection = $resource->getConnection();

        foreach (array_chunk($backlogIndexVersions, 1_000, true) as $indexVersionBatch) {
            $connection->update(
                $resource->getMainTable(),
                [
                    EmbeddingBacklogInterface::FULL_REINDEX_STATUS =>
                        FullReindexStatus::Indexed->value,
                ],
                [
                    $this->createBacklogIndexVersionCondition($connection, $indexVersionBatch),
                    EmbeddingBacklogInterface::FULL_REINDEX_STATUS . ' = ?' =>
                        FullReindexStatus::Pending->value,
                ]
            );
        }
    }

    /**
     * @return array{total: int, indexed: int, unfinished: int}
     */
    public function getFullReindexProgress(int $indexVersion): array
    {
        $this->validateIndexVersion($indexVersion);
        $resource = $this->getResource();
        /** @var AdapterInterface $connection */
        $connection = $resource->getConnection();
        $pendingStatus = $connection->quoteInto('?', Status::Pending->value);
        $select = $connection->select()
            ->from(
                $resource->getMainTable(),
                [
                    'total' => new Expression('COUNT(*)'),
                    'indexed' => new Expression(sprintf(
                        'COALESCE(SUM(%s = %d), 0)',
                        EmbeddingBacklogInterface::FULL_REINDEX_STATUS,
                        FullReindexStatus::Indexed->value
                    )),
                    'unfinished' => new Expression(sprintf(
                        'COALESCE(SUM(%s = %d AND %s = %s), 0)',
                        EmbeddingBacklogInterface::FULL_REINDEX_STATUS,
                        FullReindexStatus::Pending->value,
                        EmbeddingBacklogInterface::STATUS,
                        $pendingStatus
                    )),
                ]
            )
            ->where(EmbeddingBacklogInterface::INDEX_VERSION . ' = ?', $indexVersion)
            ->where(
                EmbeddingBacklogInterface::FULL_REINDEX_STATUS . ' IN (?)',
                [FullReindexStatus::Pending->value, FullReindexStatus::Indexed->value]
            );
        $row = $connection->fetchRow($select);

        if (!is_array($row)) {
            throw new UnexpectedValueException(
                'The full reindex progress query returned an invalid result.'
            );
        }

        return [
            'total' => $this->toNonNegativeInteger($row['total'] ?? null, 'total'),
            'indexed' => $this->toNonNegativeInteger($row['indexed'] ?? null, 'indexed'),
            'unfinished' => $this->toNonNegativeInteger(
                $row['unfinished'] ?? null,
                'unfinished'
            ),
        ];
    }

    public function deleteItemsOutsideIndexVersion(int $indexVersion): int
    {
        $this->validateIndexVersion($indexVersion);
        $resource = $this->getResource();
        /** @var AdapterInterface $connection */
        $connection = $resource->getConnection();

        return $connection->delete(
            $resource->getMainTable(),
            $connection->quoteInto(
                EmbeddingBacklogInterface::INDEX_VERSION . ' <> ?',
                $indexVersion
            )
        );
    }

    /**
     * @param array<int, int> $backlogIndexVersions
     */
    private function createBacklogIndexVersionCondition(
        AdapterInterface $connection,
        array $backlogIndexVersions
    ): string {
        $pairs = [];

        foreach ($backlogIndexVersions as $backlogId => $indexVersion) {
            $pairs[] = sprintf('(%d, %d)', $backlogId, $indexVersion);
        }

        return sprintf(
            '(%s, %s) IN (%s)',
            $connection->quoteIdentifier(EmbeddingBacklogInterface::BACKLOG_ID),
            $connection->quoteIdentifier(EmbeddingBacklogInterface::INDEX_VERSION),
            implode(', ', $pairs)
        );
    }

    private function validateIndexVersion(int $indexVersion): void
    {
        if ($indexVersion < 1) {
            throw new InvalidArgumentException('The OpenSearch index version must be positive.');
        }
    }

    private function toNonNegativeInteger(mixed $value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer < 0) {
            throw new UnexpectedValueException(
                sprintf('The full reindex progress field "%s" is invalid.', $field)
            );
        }

        return $integer;
    }

    private function getResource(): EmbeddingBacklogResource
    {
        $this->resource ??= $this->collectionFactory->create()->getResourceModel();

        return $this->resource;
    }
}
