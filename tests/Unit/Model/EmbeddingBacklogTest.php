<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Model;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklog;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use UnexpectedValueException;

class EmbeddingBacklogTest extends TestCase
{
    private EmbeddingBacklog $embeddingBacklog;

    protected function setUp(): void
    {
        $reflection = new ReflectionClass(EmbeddingBacklog::class);
        $this->embeddingBacklog = $reflection->newInstanceWithoutConstructor();
    }

    public function testMapsPersistedDataToTheServiceContract(): void
    {
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::BACKLOG_ID, '12');
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::CHUNK_ID, '42');
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::OPERATION, 'deletion');
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::STATUS, 'failed');
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::ATTEMPT_COUNT, '3');
        $this->embeddingBacklog->setLastErrorCategory('provider_unavailable');
        $this->embeddingBacklog->setCreatedAt('2026-07-29 10:00:00');
        $this->embeddingBacklog->setUpdatedAt('2026-07-29 11:00:00');

        self::assertSame(12, $this->embeddingBacklog->getBacklogId());
        self::assertSame(42, $this->embeddingBacklog->getChunkId());
        self::assertSame(Operation::Deletion, $this->embeddingBacklog->getOperation());
        self::assertSame(Status::Failed, $this->embeddingBacklog->getStatus());
        self::assertSame(3, $this->embeddingBacklog->getAttemptCount());
        self::assertSame('provider_unavailable', $this->embeddingBacklog->getLastErrorCategory());
        self::assertSame('2026-07-29 10:00:00', $this->embeddingBacklog->getCreatedAt());
        self::assertSame('2026-07-29 11:00:00', $this->embeddingBacklog->getUpdatedAt());
    }

    public function testStoresEnumValuesThroughTheServiceContract(): void
    {
        $this->embeddingBacklog->setOperation(Operation::Upsert);
        $this->embeddingBacklog->setStatus(Status::Pending);

        self::assertSame('upsert', $this->embeddingBacklog->getData(EmbeddingBacklogInterface::OPERATION));
        self::assertSame('pending', $this->embeddingBacklog->getData(EmbeddingBacklogInterface::STATUS));
        self::assertSame(Operation::Upsert, $this->embeddingBacklog->getOperation());
        self::assertSame(Status::Pending, $this->embeddingBacklog->getStatus());
    }

    public function testReturnsNullForUnsetOptionalFields(): void
    {
        self::assertNull($this->embeddingBacklog->getBacklogId());
        self::assertNull($this->embeddingBacklog->getLastErrorCategory());
        self::assertNull($this->embeddingBacklog->getCreatedAt());
        self::assertNull($this->embeddingBacklog->getUpdatedAt());
    }

    public function testRejectsAnInvalidPersistedInteger(): void
    {
        $this->embeddingBacklog->setData(
            EmbeddingBacklogInterface::CHUNK_ID,
            'not-an-integer'
        );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Embedding backlog field "chunk_id" is not an integer.'
        );

        $this->embeddingBacklog->getChunkId();
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidOperations(): iterable
    {
        yield 'non-string' => [
            null,
            'Embedding backlog operation is not a string.',
        ];
        yield 'unknown value' => [
            'unknown',
            'Embedding backlog operation "unknown" is invalid.',
        ];
    }

    #[DataProvider('invalidOperations')]
    public function testRejectsAnInvalidOperation(mixed $value, string $message): void
    {
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::OPERATION, $value);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        $this->embeddingBacklog->getOperation();
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidStatuses(): iterable
    {
        yield 'non-string' => [
            null,
            'Embedding backlog status is not a string.',
        ];
        yield 'unknown value' => [
            'unknown',
            'Embedding backlog status "unknown" is invalid.',
        ];
    }

    #[DataProvider('invalidStatuses')]
    public function testRejectsAnInvalidStatus(mixed $value, string $message): void
    {
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::STATUS, $value);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        $this->embeddingBacklog->getStatus();
    }

    public function testRejectsAnInvalidOptionalString(): void
    {
        $this->embeddingBacklog->setData(
            EmbeddingBacklogInterface::LAST_ERROR_CATEGORY,
            123
        );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Embedding backlog field "last_error_category" is not a string.'
        );

        $this->embeddingBacklog->getLastErrorCategory();
    }
}
