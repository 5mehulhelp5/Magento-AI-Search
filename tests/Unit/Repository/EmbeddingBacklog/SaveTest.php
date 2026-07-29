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
use DavidBel\AiSearch\Repository\EmbeddingBacklog\Save;
use Exception;
use Magento\Framework\Exception\CouldNotSaveException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SaveTest extends TestCase
{
    public function testSavesAnEmbeddingBacklogModel(): void
    {
        $embeddingBacklog = $this->createEmbeddingBacklog();
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('save')
            ->with($embeddingBacklog);

        self::assertSame(
            $embeddingBacklog,
            (new Save($resource))->execute($embeddingBacklog)
        );
    }

    public function testRejectsAnUnpersistableImplementation(): void
    {
        $embeddingBacklog = self::createStub(EmbeddingBacklogInterface::class);
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::never())
            ->method('save');

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage(
            'The embedding backlog implementation cannot be persisted.'
        );

        (new Save($resource))->execute($embeddingBacklog);
    }

    public function testWrapsAStorageFailure(): void
    {
        $embeddingBacklog = $this->createEmbeddingBacklog();
        $storageFailure = new Exception('storage failed');
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('save')
            ->willThrowException($storageFailure);

        try {
            (new Save($resource))->execute($embeddingBacklog);
            self::fail('A storage failure must be wrapped.');
        } catch (CouldNotSaveException $exception) {
            self::assertSame(
                'Could not save the AI search embedding backlog entry.',
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
