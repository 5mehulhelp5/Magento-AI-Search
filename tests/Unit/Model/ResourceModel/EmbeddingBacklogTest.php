<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Model\ResourceModel;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

class EmbeddingBacklogTest extends TestCase
{
    public function testQueuesAnUpsertByChunkId(): void
    {
        $this->assertQueuesOperation(Operation::Upsert);
    }

    public function testQueuesADeletionByChunkId(): void
    {
        $this->assertQueuesOperation(Operation::Deletion);
    }

    private function assertQueuesOperation(Operation $operation): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())
            ->method('insertOnDuplicate')
            ->with(
                'embedding_backlog',
                [
                    EmbeddingBacklogInterface::CHUNK_ID => 42,
                    EmbeddingBacklogInterface::OPERATION => $operation->value,
                    EmbeddingBacklogInterface::STATUS => Status::Pending->value,
                    EmbeddingBacklogInterface::ATTEMPT_COUNT => 0,
                    EmbeddingBacklogInterface::LAST_ERROR_CATEGORY => null,
                ],
                [
                    EmbeddingBacklogInterface::OPERATION,
                    EmbeddingBacklogInterface::STATUS,
                    EmbeddingBacklogInterface::ATTEMPT_COUNT,
                    EmbeddingBacklogInterface::LAST_ERROR_CATEGORY,
                ]
            );
        $resource = $this->getMockBuilder(EmbeddingBacklog::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $resource->expects(self::once())
            ->method('getConnection')
            ->willReturn($connection);
        $resource->expects(self::once())
            ->method('getMainTable')
            ->willReturn('embedding_backlog');

        if ($operation === Operation::Upsert) {
            $resource->saveByChunkId(42);
            return;
        }

        $resource->deleteByChunkId(42);
    }
}
