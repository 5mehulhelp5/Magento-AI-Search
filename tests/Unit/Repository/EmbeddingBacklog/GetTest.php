<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Repository\EmbeddingBacklog;

use DavidBel\AiSearch\Model\EmbeddingBacklog;
use DavidBel\AiSearch\Model\EmbeddingBacklogFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Repository\EmbeddingBacklog\Get;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function class_alias;
use function class_exists;

class GetTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (class_exists(EmbeddingBacklogFactory::class, false)) {
            return;
        }

        class_alias(GeneratedFactoryTestDouble::class, EmbeddingBacklogFactory::class);
    }

    public function testLoadsAnEmbeddingBacklogById(): void
    {
        $embeddingBacklog = $this->createEmbeddingBacklog();
        $factory = $this->createMock(EmbeddingBacklogFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturn($embeddingBacklog);
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('load')
            ->with($embeddingBacklog, 12)
            ->willReturnCallback(
                static function (
                    EmbeddingBacklog $model
                ) use ($resource): EmbeddingBacklogResource {
                    $model->setBacklogId(12);

                    return $resource;
                }
            );

        self::assertSame(
            $embeddingBacklog,
            (new Get($factory, $resource))->execute(12)
        );
    }

    public function testRejectsAMissingEmbeddingBacklog(): void
    {
        $embeddingBacklog = $this->createEmbeddingBacklog();
        $factory = $this->createMock(EmbeddingBacklogFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturn($embeddingBacklog);
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('load')
            ->with($embeddingBacklog, 404);

        $this->expectException(NoSuchEntityException::class);

        (new Get($factory, $resource))->execute(404);
    }

    private function createEmbeddingBacklog(): EmbeddingBacklog
    {
        $reflection = new ReflectionClass(EmbeddingBacklog::class);

        return $reflection->newInstanceWithoutConstructor();
    }
}
