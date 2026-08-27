<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Model;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklog;
use DavidBel\AiSearch\Model\EmbeddingBacklog\ErrorDetails;
use DavidBel\AiSearch\Model\EmbeddingBacklog\FullReindexStatus;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as BacklogResource;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;
use ValueError;

class EmbeddingBacklogTest extends TestCase
{
    private EmbeddingBacklog $embeddingBacklog;

    protected function setUp(): void
    {
        $this->embeddingBacklog = new EmbeddingBacklog(
            self::createStub(Context::class),
            self::createStub(Registry::class),
            self::createStub(ExtensionAttributesFactory::class),
            self::createStub(AttributeValueFactory::class),
            self::createStub(BacklogResource::class)
        );
    }

    public function testMapsPersistedDataToTheServiceContract(): void
    {
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::BACKLOG_ID, '12');
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::BACKLOG_VERSION, '4');
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::INDEX_VERSION, '7');
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::FULL_REINDEX_STATUS, '1');
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::CHUNK_ID, '42');
        $this->embeddingBacklog->setChunkId(42);
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE, 'product');
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::SOURCE_ENTITY_ID, '81');
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::OPERATION, 'delete');
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::STATUS, 'failed');
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::ATTEMPT_COUNT, '3');
        $this->embeddingBacklog->setAttemptCount(3);
        $this->embeddingBacklog->setLastErrorStage('embedder');
        $this->embeddingBacklog->setLastErrorCode('provider_unavailable');
        $this->embeddingBacklog->setLastErrorMessage('Embedding provider unavailable.');
        $this->embeddingBacklog->setCreatedAt('2026-07-29 10:00:00');
        $this->embeddingBacklog->setUpdatedAt('2026-07-29 11:00:00');

        self::assertSame(12, $this->embeddingBacklog->getBacklogId());
        self::assertSame(4, $this->embeddingBacklog->getBacklogVersion());
        self::assertSame(7, $this->embeddingBacklog->getIndexVersion());
        self::assertSame(FullReindexStatus::Pending, $this->embeddingBacklog->getFullReindexStatus());
        self::assertSame(42, $this->embeddingBacklog->getChunkId());
        self::assertSame('product', $this->embeddingBacklog->getSourceEntityType());
        self::assertSame(81, $this->embeddingBacklog->getSourceEntityId());
        self::assertSame(Operation::Delete, $this->embeddingBacklog->getOperation());
        self::assertSame(Status::Failed, $this->embeddingBacklog->getStatus());
        self::assertSame(3, $this->embeddingBacklog->getAttemptCount());
        self::assertSame('embedder', $this->embeddingBacklog->getLastErrorStage());
        self::assertSame('provider_unavailable', $this->embeddingBacklog->getLastErrorCode());
        self::assertSame(
            'Embedding provider unavailable.',
            $this->embeddingBacklog->getLastErrorMessage()
        );
        self::assertSame('2026-07-29 10:00:00', $this->embeddingBacklog->getCreatedAt());
        self::assertSame('2026-07-29 11:00:00', $this->embeddingBacklog->getUpdatedAt());
    }

    public function testErrorDetailsUsesFallbackForEmptyMessage(): void
    {
        $details = new ErrorDetails(' ', '  ');

        self::assertNull($details->code);
        self::assertSame('Processing failed.', $details->message);
    }

    public function testStoresEnumValuesThroughTheServiceContract(): void
    {
        $this->embeddingBacklog->setOperation(Operation::Upsert);
        $this->embeddingBacklog->setStatus(Status::Outdated);
        $this->embeddingBacklog->setBacklogVersion(5);
        $this->embeddingBacklog->setIndexVersion(8);
        $this->embeddingBacklog->setFullReindexStatus(FullReindexStatus::Indexed);
        $this->embeddingBacklog->setSourceEntityType('product');
        $this->embeddingBacklog->setSourceEntityId(91);

        self::assertSame('upsert', $this->embeddingBacklog->getData(EmbeddingBacklogInterface::OPERATION));
        self::assertSame('outdated', $this->embeddingBacklog->getData(EmbeddingBacklogInterface::STATUS));
        self::assertSame(5, $this->embeddingBacklog->getData(EmbeddingBacklogInterface::BACKLOG_VERSION));
        self::assertSame(8, $this->embeddingBacklog->getData(EmbeddingBacklogInterface::INDEX_VERSION));
        self::assertSame(
            FullReindexStatus::Indexed->value,
            $this->embeddingBacklog->getData(EmbeddingBacklogInterface::FULL_REINDEX_STATUS)
        );
        self::assertSame('product', $this->embeddingBacklog->getSourceEntityType());
        self::assertSame(91, $this->embeddingBacklog->getSourceEntityId());
        self::assertSame(Operation::Upsert, $this->embeddingBacklog->getOperation());
        self::assertSame(Status::Outdated, $this->embeddingBacklog->getStatus());
    }

    public function testReturnsNullForUnsetOptionalFields(): void
    {
        self::assertNull($this->embeddingBacklog->getBacklogId());
        self::assertNull($this->embeddingBacklog->getSourceEntityType());
        self::assertNull($this->embeddingBacklog->getSourceEntityId());
        self::assertNull($this->embeddingBacklog->getLastErrorStage());
        self::assertNull($this->embeddingBacklog->getLastErrorCode());
        self::assertNull($this->embeddingBacklog->getLastErrorMessage());
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
     * @return iterable<string, array{mixed, class-string<\Throwable>, string}>
     */
    public static function invalidOperations(): iterable
    {
        yield 'non-string' => [
            null,
            UnexpectedValueException::class,
            'Embedding backlog operation is not a string.',
        ];
        yield 'unknown value' => [
            'unknown',
            ValueError::class,
            '"unknown" is not a valid backing value for enum',
        ];
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    #[DataProvider('invalidOperations')]
    public function testRejectsAnInvalidOperation(
        mixed $value,
        string $exceptionClass,
        string $message
    ): void {
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::OPERATION, $value);

        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($message);

        $this->embeddingBacklog->getOperation();
    }

    /**
     * @return iterable<string, array{mixed, class-string<\Throwable>, string}>
     */
    public static function invalidStatuses(): iterable
    {
        yield 'non-string' => [
            null,
            UnexpectedValueException::class,
            'Embedding backlog status is not a string.',
        ];
        yield 'unknown value' => [
            'unknown',
            ValueError::class,
            '"unknown" is not a valid backing value for enum',
        ];
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    #[DataProvider('invalidStatuses')]
    public function testRejectsAnInvalidStatus(
        mixed $value,
        string $exceptionClass,
        string $message
    ): void {
        $this->embeddingBacklog->setData(EmbeddingBacklogInterface::STATUS, $value);

        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($message);

        $this->embeddingBacklog->getStatus();
    }

    public function testRejectsAnInvalidOptionalString(): void
    {
        $this->embeddingBacklog->setData(
            EmbeddingBacklogInterface::LAST_ERROR_STAGE,
            123
        );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Embedding backlog field "last_error_stage" is not a string.'
        );

        $this->embeddingBacklog->getLastErrorStage();
    }
}
