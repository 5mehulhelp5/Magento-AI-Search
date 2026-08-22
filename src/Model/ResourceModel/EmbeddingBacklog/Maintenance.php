<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use InvalidArgumentException;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Sql\Expression;

class Maintenance
{
    private ?EmbeddingBacklogResource $resource = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function markFailedAsPending(int $indexVersion, int $attemptThreshold): int
    {
        $this->validateIndexVersion($indexVersion);

        if ($attemptThreshold < 1) {
            throw new InvalidArgumentException('The retry attempt threshold must be positive.');
        }

        $resource = $this->getResource();
        /** @var AdapterInterface $connection */
        $connection = $resource->getConnection();

        return $connection->update(
            $resource->getMainTable(),
            [EmbeddingBacklogInterface::STATUS => Status::Pending->value],
            [
                EmbeddingBacklogInterface::STATUS . ' = ?' => Status::Failed->value,
                EmbeddingBacklogInterface::INDEX_VERSION . ' = ?' => $indexVersion,
                EmbeddingBacklogInterface::ATTEMPT_COUNT . ' < ?' => $attemptThreshold,
            ]
        );
    }

    public function markMissingChunkUpsertsOutdated(int $indexVersion): int
    {
        $this->validateIndexVersion($indexVersion);
        $resource = $this->getResource();
        /** @var AdapterInterface $connection */
        $connection = $resource->getConnection();
        $select = $connection->select()
            ->joinLeft(
                ['chunk' => $resource->getTable('davidbel_ai_search_chunk')],
                'chunk.chunk_id = backlog.chunk_id',
                []
            )
            ->columns([
                EmbeddingBacklogInterface::STATUS => new Expression(
                    $connection->quoteInto('?', Status::Outdated->value)
                ),
                EmbeddingBacklogInterface::LAST_ERROR_STAGE => new Expression('NULL'),
                EmbeddingBacklogInterface::LAST_ERROR_CODE => new Expression('NULL'),
                EmbeddingBacklogInterface::LAST_ERROR_MESSAGE => new Expression('NULL'),
            ])
            ->where('backlog.operation = ?', Operation::Upsert->value)
            ->where('backlog.status = ?', Status::Pending->value)
            ->where('backlog.index_version = ?', $indexVersion)
            ->where('chunk.chunk_id IS NULL');
        $query = $connection->updateFromSelect(
            $select,
            ['backlog' => $resource->getMainTable()]
        );

        return $connection->query($query)->rowCount();
    }

    public function deleteExhaustedUpsertsOrExpiredResults(
        int $attemptThreshold,
        string $expiredBefore,
        ?int $protectedIndexVersion = null
    ): int {
        if ($attemptThreshold < 1) {
            throw new InvalidArgumentException('The cleanup attempt threshold must be positive.');
        }

        if ($expiredBefore === '') {
            throw new InvalidArgumentException('The backlog expiration cutoff must not be empty.');
        }

        if ($protectedIndexVersion !== null) {
            $this->validateIndexVersion($protectedIndexVersion);
        }

        $resource = $this->getResource();
        /** @var AdapterInterface $connection */
        $connection = $resource->getConnection();
        $cleanupCondition = $this->createCleanupCondition(
            $connection,
            $attemptThreshold,
            $expiredBefore
        );

        if ($protectedIndexVersion !== null) {
            $cleanupCondition = sprintf(
                '(%s) AND %s',
                $cleanupCondition,
                $connection->quoteInto(
                    EmbeddingBacklogInterface::INDEX_VERSION . ' <> ?',
                    $protectedIndexVersion
                )
            );
        }

        return $connection->delete($resource->getMainTable(), $cleanupCondition);
    }

    private function createCleanupCondition(
        AdapterInterface $connection,
        int $attemptThreshold,
        string $expiredBefore
    ): string {
        $failedStatus = $connection->quoteInto(
            EmbeddingBacklogInterface::STATUS . ' = ?',
            Status::Failed->value
        );
        $attemptLimit = $connection->quoteInto(
            EmbeddingBacklogInterface::ATTEMPT_COUNT . ' >= ?',
            $attemptThreshold
        );
        $resultStatus = $connection->quoteInto(
            EmbeddingBacklogInterface::STATUS . ' IN (?)',
            [
                Status::Done->value,
                Status::Outdated->value,
            ]
        );
        $expirationCutoff = $connection->quoteInto(
            EmbeddingBacklogInterface::UPDATED_AT . ' < ?',
            $expiredBefore
        );

        return sprintf(
            '((%s AND %s) OR (%s AND %s))',
            $failedStatus,
            $attemptLimit,
            $resultStatus,
            $expirationCutoff
        );
    }

    private function validateIndexVersion(int $indexVersion): void
    {
        if ($indexVersion < 1) {
            throw new InvalidArgumentException('The OpenSearch index version must be positive.');
        }
    }

    private function getResource(): EmbeddingBacklogResource
    {
        $this->resource ??= $this->collectionFactory->create()->getResourceModel();

        return $this->resource;
    }
}
