<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Repository\EmbeddingBacklog;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklog;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Repository\EmbeddingBacklog\DeleteById;
use DavidBel\AiSearch\Repository\EmbeddingBacklog\Get;
use Exception;
use Magento\Framework\Exception\CouldNotDeleteException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DeleteByIdTest extends TestCase
{
    public function testDeletesAnEmbeddingBacklogById(): void
    {
        $embeddingBacklog = $this->createEmbeddingBacklog();
        $get = $this->createMock(Get::class);
        $get->expects(self::once())
            ->method('execute')
            ->with(12)
            ->willReturn($embeddingBacklog);
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('delete')
            ->with($embeddingBacklog);

        self::assertTrue((new DeleteById($get, $resource))->execute(12));
    }

    public function testRejectsAnUndeletableImplementation(): void
    {
        $get = $this->createMock(Get::class);
        $get->expects(self::once())
            ->method('execute')
            ->with(12)
            ->willReturn(self::createStub(EmbeddingBacklogInterface::class));
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::never())
            ->method('delete');

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage(
            'The embedding backlog implementation cannot be deleted.'
        );

        (new DeleteById($get, $resource))->execute(12);
    }

    public function testWrapsAStorageFailure(): void
    {
        $embeddingBacklog = $this->createEmbeddingBacklog();
        $get = $this->createMock(Get::class);
        $get->expects(self::once())
            ->method('execute')
            ->with(12)
            ->willReturn($embeddingBacklog);
        $storageFailure = new Exception('storage failed');
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('delete')
            ->willThrowException($storageFailure);

        try {
            (new DeleteById($get, $resource))->execute(12);
            self::fail('A storage failure must be wrapped.');
        } catch (CouldNotDeleteException $exception) {
            self::assertSame(
                'Could not delete the AI search embedding backlog entry.',
                $exception->getMessage()
            );
            self::assertSame($storageFailure, $exception->getPrevious());
        }
    }

    private function createEmbeddingBacklog(): EmbeddingBacklog
    {
        $reflection = new ReflectionClass(EmbeddingBacklog::class);

        return $reflection->newInstanceWithoutConstructor();
    }
}
