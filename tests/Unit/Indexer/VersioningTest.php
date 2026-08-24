<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Indexer;

use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Config\SemanticDataProcessingConfig;
use DavidBel\AiSearch\Config\EmbeddedAttribute;
use DavidBel\AiSearch\Config\EmbeddedAttributesConfig;
use DavidBel\AiSearch\Config\EmbedderConfig;
use DavidBel\AiSearch\Config\IndexingScopeConfig;
use DavidBel\AiSearch\Config\SearchConfig;
use DavidBel\AiSearch\Config\SemanticSearchResultConfig;
use DavidBel\AiSearch\Indexer\Versioning\ConfigurationFingerprint;
use DavidBel\AiSearch\Indexer\Versioning\IndexName;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex\QueryConfigurationSnapshot;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndexDelete;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndexProvider;
use DavidBel\AiSearch\Indexer\Versioning\ProductIndexerInvalidation;
use DavidBel\AiSearch\Indexer\Versioning\State;
use DavidBel\AiSearch\Indexer\Versioning\State\CacheStatus;
use DavidBel\AiSearch\Indexer\Versioning\State\Flag;
use DavidBel\AiSearch\Indexer\Versioning\State\Mapper;
use DavidBel\AiSearch\Indexer\Versioning\Target;
use DavidBel\AiSearch\Indexer\Versioning\Target\Activation;
use DavidBel\AiSearch\Indexer\Versioning\Target\Activation\CacheClean;
use DavidBel\AiSearch\Indexer\Versioning\Target\Activation\Readiness;
use DavidBel\AiSearch\Indexer\Versioning\Target\Preparation;
use DavidBel\AiSearch\Indexer\Versioning\VersionLock;
use DavidBel\AiSearch\Log\Logger;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\IndexVersion as BacklogIndexVersion;
use InvalidArgumentException;
use Magento\Elasticsearch\Model\Config as MagentoSearchConfig;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\FlagManager;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

/**
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
class VersioningTest extends TestCase
{
    public function testIndexNameUsesOptionalPrefixAndValidatesVersion(): void
    {
        $searchConfig = self::createStub(SearchConfig::class);
        $searchConfig->method('getIndexName')->willReturn('ai_search');
        $prefixedConfig = self::createStub(MagentoSearchConfig::class);
        $prefixedConfig->method('getIndexPrefix')->willReturn(' magento ');
        $plainConfig = self::createStub(MagentoSearchConfig::class);
        $plainConfig->method('getIndexPrefix')->willReturn('  ');

        self::assertSame(
            'magento_ai_search_v2',
            (new IndexName($prefixedConfig, $searchConfig))->getVersionName(2)
        );
        self::assertSame('ai_search', (new IndexName($plainConfig, $searchConfig))->getAlias());

        $this->expectException(UnexpectedValueException::class);
        (new IndexName($plainConfig, $searchConfig))->getVersionName(0);
    }

    public function testPhysicalIndexAndStateSerializeAllData(): void
    {
        $physicalIndex = $this->physicalIndex();
        $target = new Target($physicalIndex, true);
        $state = new State($physicalIndex, $target, CacheStatus::Required);

        self::assertSame(
            [
                'active' => $physicalIndex->toArray(),
                'target' => [
                    'version' => $physicalIndex->toArray(),
                    'document_processing_completed' => true,
                ],
                'cache_status' => 'required',
            ],
            $state->toArray()
        );
        self::assertSame(
            ['active' => null, 'target' => null, 'cache_status' => 'clean'],
            (new State())->toArray()
        );
    }

    /**
     * @param array{int, string, string} $values
     */
    #[DataProvider('invalidPhysicalIndexes')]
    public function testPhysicalIndexRejectsInvalidValues(array $values): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PhysicalIndex(
            $values[0],
            $values[1],
            $values[2],
            new QueryConfigurationSnapshot('model', 3, '{text}')
        );
    }

    /**
     * @return array<string, array{array{int, string, string}}>
     */
    public static function invalidPhysicalIndexes(): array
    {
        return [
            'number' => [[0, 'index', 'fingerprint']],
            'name' => [[1, '', 'fingerprint']],
            'fingerprint' => [[1, 'index', '']],
        ];
    }

    public function testConfigurationFingerprintSerializesCompleteConfiguration(): void
    {
        $child = new EmbeddedAttribute('color', true, 'html_to_text', 'Color: {color}', null);
        $attribute = new EmbeddedAttribute(
            'embedding_template',
            false,
            'text_as_is',
            null,
            [$child]
        );
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())
            ->method('serialize')
            ->with($this->expectedFingerprintConfiguration($attribute))
            ->willReturn('serialized configuration');

        self::assertSame(
            hash('sha256', 'serialized configuration'),
            $this->createConfigurationFingerprint($serializer, $attribute)->get()
        );
    }

    public function testConfigurationFingerprintUsesNullForDisabledTitle(): void
    {
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('serialize')->willReturn('configuration');
        $attributes = self::createStub(EmbeddedAttributesConfig::class);
        $attributes->method('isDocumentTitleEnabled')->willReturn(false);
        $scope = self::createStub(IndexingScopeConfig::class);
        $scope->method('getStoreIdsForIndexing')->willReturn([]);

        self::assertSame(
            hash('sha256', 'configuration'),
            $this->createConfigurationFingerprint($serializer, null, $attributes, $scope)->get()
        );
    }

    public function testConfigurationFingerprintRejectsNonStringSerialization(): void
    {
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('serialize')->willReturn(false);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('could not be serialized');

        $this->createConfigurationFingerprint($serializer)->get();
    }

    public function testStateMapperMapsCompleteAndDefaultState(): void
    {
        $mapper = new Mapper();

        self::assertEquals(new State(), $mapper->map([]));
        self::assertEquals(
            new State(
                $this->physicalIndex(),
                new Target($this->physicalIndex(), true),
                CacheStatus::Required
            ),
            $mapper->map([
                'active' => $this->physicalIndex()->toArray(),
                'target' => [
                    'version' => $this->physicalIndex()->toArray(),
                    'document_processing_completed' => true,
                ],
                'cache_status' => 'required',
            ])
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('invalidStoredStates')]
    public function testStateMapperRejectsInvalidStoredState(array $data, string $message): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        (new Mapper())->map($data);
    }

    /**
     * @return array<string, array{array<array-key, mixed>, string}>
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public static function invalidStoredStates(): array
    {
        $validVersion = self::staticPhysicalIndexData();

        return [
            'active type' => [['active' => 'invalid'], 'version state is invalid'],
            'target type' => [['target' => 'invalid'], 'version state is invalid'],
            'cache type' => [['cache_status' => 1], 'cache status is invalid'],
            'cache value' => [['cache_status' => 'invalid'], 'cache status is invalid'],
            'target version' => [
                ['target' => ['version' => null, 'document_processing_completed' => true]],
                'target index version is invalid',
            ],
            'target completion' => [
                ['target' => ['version' => $validVersion, 'document_processing_completed' => 1]],
                'target index version is invalid',
            ],
            'version number' => [
                ['active' => [...$validVersion, 'number' => '1']],
                'stored search index version is invalid',
            ],
            'version name' => [
                ['active' => [...$validVersion, 'index_name' => null]],
                'stored search index version is invalid',
            ],
            'version fingerprint' => [
                ['active' => [...$validVersion, 'configuration_fingerprint' => null]],
                'stored search index version is invalid',
            ],
            'query configuration type' => [
                ['active' => [...$validVersion, 'query_configuration' => null]],
                'stored search index version is invalid',
            ],
            'query model' => [
                [
                    'active' => [
                        ...$validVersion,
                        'query_configuration' => [
                            'embedding_model' => null,
                            'vector_dimensions' => 3,
                            'query_template' => '{text}',
                        ],
                    ],
                ],
                'query configuration snapshot is invalid',
            ],
            'query dimensions' => [
                [
                    'active' => [
                        ...$validVersion,
                        'query_configuration' => [
                            'embedding_model' => 'model',
                            'vector_dimensions' => '3',
                            'query_template' => '{text}',
                        ],
                    ],
                ],
                'query configuration snapshot is invalid',
            ],
            'query template' => [
                [
                    'active' => [
                        ...$validVersion,
                        'query_configuration' => [
                            'embedding_model' => 'model',
                            'vector_dimensions' => 3,
                            'query_template' => null,
                        ],
                    ],
                ],
                'query configuration snapshot is invalid',
            ],
        ];
    }

    public function testStateFlagReturnsDefaultMapsAndSaves(): void
    {
        $manager = $this->createMock(FlagManager::class);
        $manager->expects(self::exactly(2))
            ->method('getFlagData')
            ->with('davidbel_ai_search_index_version')
            ->willReturnOnConsecutiveCalls(null, ['cache_status' => 'required']);
        $manager->expects(self::once())
            ->method('saveFlag')
            ->with(
                'davidbel_ai_search_index_version',
                ['active' => null, 'target' => null, 'cache_status' => 'clean']
            );
        $flag = new Flag($manager, new Mapper());

        self::assertEquals(new State(), $flag->get());
        self::assertEquals(new State(null, null, CacheStatus::Required), $flag->get());
        $flag->save(new State());
    }

    public function testStateFlagRejectsNonArrayData(): void
    {
        $manager = self::createStub(FlagManager::class);
        $manager->method('getFlagData')->willReturn('invalid');

        $this->expectException(UnexpectedValueException::class);

        (new Flag($manager, new Mapper()))->get();
    }

    public function testPhysicalIndexProviderSelectsIndexesForEachOperation(): void
    {
        $active = $this->physicalIndex();
        $targetIndex = $this->physicalIndex(2, 'target', 'current');
        $target = new Target($targetIndex, false);
        $flag = self::createStub(Flag::class);
        $flag->method('get')->willReturn(new State($active, $target));
        $fingerprint = self::createStub(ConfigurationFingerprint::class);
        $fingerprint->method('get')->willReturn('current');
        $provider = new PhysicalIndexProvider($flag, $fingerprint);

        self::assertSame($targetIndex, $provider->getTarget());
        self::assertSame($targetIndex, $provider->getTargetForCurrentConfiguration());
        self::assertSame($active, $provider->getActive());
        self::assertNull($provider->getActiveForCurrentConfiguration());
        self::assertSame($targetIndex, $provider->getForIngestion());
        self::assertNull($provider->getForSearch(false));
        self::assertSame($active, $provider->getForSearch(true));
    }

    public function testPhysicalIndexProviderHandlesEmptyAndMatchingActiveState(): void
    {
        $flag = self::createStub(Flag::class);
        $flag->method('get')->willReturn(new State());
        $fingerprint = self::createStub(ConfigurationFingerprint::class);
        $provider = new PhysicalIndexProvider($flag, $fingerprint);

        self::assertNull($provider->getTargetForCurrentConfiguration());
        self::assertNull($provider->getActiveForCurrentConfiguration());
        self::assertNull($provider->getForIngestion());

        $active = $this->physicalIndex();
        $activeFlag = self::createStub(Flag::class);
        $activeFlag->method('get')->willReturn(new State($active));
        $matching = self::createStub(ConfigurationFingerprint::class);
        $matching->method('get')->willReturn('fingerprint');
        self::assertSame(
            $active,
            (new PhysicalIndexProvider($activeFlag, $matching))->getActiveForCurrentConfiguration()
        );
    }

    public function testVersionLockDelegatesToLockManager(): void
    {
        $manager = $this->createMock(LockManagerInterface::class);
        $manager->expects(self::once())
            ->method('lock')
            ->with('davidbel_ai_search_index_version', 10)
            ->willReturn(true);
        $manager->expects(self::once())
            ->method('unlock')
            ->with('davidbel_ai_search_index_version');
        $lock = new VersionLock($manager);

        self::assertTrue($lock->lock(10));
        $lock->unlock();
    }

    public function testCacheCleanDelegatesToFullPageCache(): void
    {
        $types = $this->createMock(TypeListInterface::class);
        $types->expects(self::once())->method('cleanType')->with('full_page');

        (new CacheClean($types))->execute();
    }

    /**
     * @param array{total: int, indexed: int, unfinished: int} $progress
     */
    #[DataProvider('readinessCases')]
    public function testReadinessUsesProgressAndThreshold(array $progress, bool $expected): void
    {
        $backlog = self::createStub(BacklogIndexVersion::class);
        $backlog->method('getFullReindexProgress')->willReturn($progress);
        $config = self::createStub(SemanticDataProcessingConfig::class);
        $config->method('getIndexerMinimumSuccessPercentage')->willReturn(80);

        self::assertSame($expected, (new Readiness($backlog, $config))->isReady(2));
    }

    /**
     * @return array<string, array{array{total: int, indexed: int, unfinished: int}, bool}>
     */
    public static function readinessCases(): array
    {
        return [
            'unfinished' => [['total' => 10, 'indexed' => 9, 'unfinished' => 1], false],
            'empty' => [['total' => 0, 'indexed' => 0, 'unfinished' => 0], true],
            'above threshold' => [['total' => 10, 'indexed' => 8, 'unfinished' => 0], true],
            'below threshold' => [['total' => 10, 'indexed' => 7, 'unfinished' => 0], false],
        ];
    }

    public function testProductIndexerInvalidationSkipsWhenAnIndexMatches(): void
    {
        $provider = self::createStub(PhysicalIndexProvider::class);
        $provider->method('getTargetForCurrentConfiguration')->willReturn($this->physicalIndex());
        $registry = $this->createMock(IndexerRegistry::class);
        $registry->expects(self::never())->method('get');

        (new ProductIndexerInvalidation($provider, $registry))->execute();
    }

    /**
     * @param array{bool, bool} $status
     */
    #[DataProvider('busyIndexerStatuses')]
    public function testProductIndexerInvalidationSkipsBusyIndexer(array $status): void
    {
        $provider = self::createStub(PhysicalIndexProvider::class);
        $indexer = $this->createMock(IndexerInterface::class);
        $indexer->method('isInvalid')->willReturn($status[0]);
        $indexer->method('isWorking')->willReturn($status[1]);
        $indexer->expects(self::never())->method('invalidate');

        (new ProductIndexerInvalidation($provider, $this->createIndexerRegistry($indexer)))
            ->execute();
    }

    /**
     * @return array<string, array{array{bool, bool}}>
     */
    public static function busyIndexerStatuses(): array
    {
        return [
            'invalid' => [[true, false]],
            'working' => [[false, true]],
        ];
    }

    public function testProductIndexerInvalidationInvalidatesReadyIndexer(): void
    {
        $provider = self::createStub(PhysicalIndexProvider::class);
        $indexer = $this->createMock(IndexerInterface::class);
        $indexer->method('isInvalid')->willReturn(false);
        $indexer->method('isWorking')->willReturn(false);
        $indexer->expects(self::once())->method('invalidate');

        (new ProductIndexerInvalidation($provider, $this->createIndexerRegistry($indexer)))
            ->execute();
    }

    public function testPhysicalIndexDeleteSkipsWithoutStableActiveIndex(): void
    {
        $flag = self::createStub(Flag::class);
        $flag->method('get')->willReturn(new State(null, new Target($this->physicalIndex(), false)));
        $openSearch = $this->createMock(OpenSearch::class);
        $openSearch->expects(self::never())->method('getVersionIndexNames');

        self::assertSame(0, (new PhysicalIndexDelete(
            $flag,
            $openSearch,
            self::createStub(Logger::class)
        ))->execute());
    }

    public function testPhysicalIndexDeleteRemovesOnlyObsoleteIndexes(): void
    {
        $flag = self::createStub(Flag::class);
        $flag->method('get')->willReturn(new State($this->physicalIndex(2, 'alias_v2')));
        $openSearch = $this->createMock(OpenSearch::class);
        $openSearch->method('getVersionIndexNames')->willReturn(['alias_v1', 'alias_v2']);
        $openSearch->expects(self::once())->method('deleteIndex')->with('alias_v1');

        self::assertSame(1, (new PhysicalIndexDelete(
            $flag,
            $openSearch,
            self::createStub(Logger::class)
        ))->execute());
    }

    public function testPhysicalIndexDeleteLogsListingFailure(): void
    {
        $failure = new RuntimeException('listing failed');
        $flag = self::createStub(Flag::class);
        $flag->method('get')->willReturn(new State($this->physicalIndex(2, 'alias_v2')));
        $openSearch = self::createStub(OpenSearch::class);
        $openSearch->method('getVersionIndexNames')->willThrowException($failure);
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('physicalIndexListingFailed')->with($failure);

        self::assertSame(0, (new PhysicalIndexDelete($flag, $openSearch, $logger))->execute());
    }

    public function testPhysicalIndexDeleteLogsDeleteFailureAndContinues(): void
    {
        $failure = new RuntimeException('delete failed');
        $flag = self::createStub(Flag::class);
        $flag->method('get')->willReturn(new State($this->physicalIndex(2, 'alias_v2')));
        $openSearch = self::createStub(OpenSearch::class);
        $openSearch->method('getVersionIndexNames')->willReturn(['alias_v1']);
        $openSearch->method('deleteIndex')->willThrowException($failure);
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())
            ->method('physicalIndexDeleteFailed')
            ->with('alias_v1', $failure);

        self::assertSame(0, (new PhysicalIndexDelete($flag, $openSearch, $logger))->execute());
    }

    public function testPreparationCreatesAndPersistsNextIndex(): void
    {
        $state = new State($this->physicalIndex(2));
        $flag = $this->createMock(Flag::class);
        $flag->method('get')->willReturn($state);
        $flag->expects(self::once())->method('save');
        $openSearch = $this->createMock(OpenSearch::class);
        $openSearch->method('indexExists')->willReturn(false);
        $openSearch->expects(self::once())->method('createIndex');
        $lock = $this->createSuccessfulLock();

        $this->createPreparation($flag, $openSearch, $lock)->prepare();
    }

    public function testPreparationResumesMatchingTargetAndResetsCompletion(): void
    {
        $target = new Target($this->physicalIndex(2), true);
        $flag = $this->createMock(Flag::class);
        $flag->method('get')->willReturn(new State(null, $target, CacheStatus::Required));
        $flag->expects(self::once())
            ->method('save')
            ->with(new State(null, new Target($target->physicalIndex, false), CacheStatus::Required));
        $openSearch = $this->createMock(OpenSearch::class);
        $openSearch->expects(self::once())->method('createIndex')->with($target->physicalIndex);

        $this->createPreparation($flag, $openSearch, $this->createSuccessfulLock())->prepare();
    }

    public function testPreparationRejectsUnavailableLock(): void
    {
        $lock = self::createStub(VersionLock::class);
        $lock->method('lock')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('currently being changed');

        $this->createPreparation(
            self::createStub(Flag::class),
            self::createStub(OpenSearch::class),
            $lock
        )->prepare();
    }

    public function testPreparationMarksDocumentProcessingComplete(): void
    {
        $target = new Target($this->physicalIndex(), false);
        $flag = $this->createMock(Flag::class);
        $flag->method('get')->willReturn(new State(null, $target));
        $flag->expects(self::once())
            ->method('save')
            ->with(new State(null, new Target($target->physicalIndex, true)));

        $this->createPreparation(
            $flag,
            self::createStub(OpenSearch::class),
            $this->createSuccessfulLock()
        )->markDocumentProcessingComplete();
    }

    public function testPreparationRejectsCompletionWithoutTargetAndUnlocks(): void
    {
        $flag = self::createStub(Flag::class);
        $flag->method('get')->willReturn(new State());
        $lock = $this->createMock(VersionLock::class);
        $lock->method('lock')->willReturn(true);
        $lock->expects(self::once())->method('unlock');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has not been prepared');

        $this->createPreparation($flag, self::createStub(OpenSearch::class), $lock)
            ->markDocumentProcessingComplete();
    }

    public function testActivationReturnsFalseWhenLockIsUnavailable(): void
    {
        $lock = self::createStub(VersionLock::class);
        $lock->method('lock')->willReturn(false);

        self::assertFalse($this->createActivation(versionLock: $lock)->execute());
    }

    public function testActivationReturnsFalseWithoutCompletedTarget(): void
    {
        $flag = self::createStub(Flag::class);
        $flag->method('get')->willReturn(new State());

        self::assertFalse($this->createActivation(
            stateFlag: $flag,
            versionLock: $this->createSuccessfulLock()
        )->execute());
    }

    public function testActivationRejectsChangedConfigurationOrMissingIndex(): void
    {
        $target = new Target($this->physicalIndex(), true);
        $flag = self::createStub(Flag::class);
        $flag->method('get')->willReturn(new State(null, $target));
        $fingerprint = self::createStub(ConfigurationFingerprint::class);
        $fingerprint->method('get')->willReturn('changed');

        self::assertFalse($this->createActivation(
            configurationFingerprint: $fingerprint,
            stateFlag: $flag,
            versionLock: $this->createSuccessfulLock()
        )->execute());

        $matching = self::createStub(ConfigurationFingerprint::class);
        $matching->method('get')->willReturn('fingerprint');
        $openSearch = self::createStub(OpenSearch::class);
        $openSearch->method('indexExists')->willReturn(false);
        self::assertFalse($this->createActivation(
            configurationFingerprint: $matching,
            stateFlag: $flag,
            openSearch: $openSearch,
            versionLock: $this->createSuccessfulLock()
        )->execute());
    }

    public function testActivationReturnsFalseWhenTargetIsNotReady(): void
    {
        $target = new Target($this->physicalIndex(), true);
        $flag = self::createStub(Flag::class);
        $flag->method('get')->willReturn(new State(null, $target));
        $fingerprint = self::createStub(ConfigurationFingerprint::class);
        $fingerprint->method('get')->willReturn('fingerprint');
        $openSearch = self::createStub(OpenSearch::class);
        $openSearch->method('indexExists')->willReturn(true);
        $readiness = self::createStub(Readiness::class);
        $readiness->method('isReady')->willReturn(false);

        self::assertFalse($this->createActivation(
            configurationFingerprint: $fingerprint,
            stateFlag: $flag,
            openSearch: $openSearch,
            readiness: $readiness,
            versionLock: $this->createSuccessfulLock()
        )->execute());
    }

    public function testActivationActivatesReadyTargetAndCleansCache(): void
    {
        $target = new Target($this->physicalIndex(), true);
        $flag = $this->createMock(Flag::class);
        $flag->method('get')->willReturn(new State(null, $target));
        $flag->expects(self::exactly(2))->method('save');
        $fingerprint = self::createStub(ConfigurationFingerprint::class);
        $fingerprint->method('get')->willReturn('fingerprint');
        $openSearch = $this->createMock(OpenSearch::class);
        $openSearch->method('indexExists')->willReturn(true);
        $openSearch->expects(self::once())->method('activateIndex')->with($target->physicalIndex);
        $readiness = self::createStub(Readiness::class);
        $readiness->method('isReady')->willReturn(true);
        $cacheClean = $this->createMock(CacheClean::class);
        $cacheClean->expects(self::once())->method('execute');

        self::assertTrue($this->createActivation(
            configurationFingerprint: $fingerprint,
            stateFlag: $flag,
            openSearch: $openSearch,
            readiness: $readiness,
            cacheClean: $cacheClean,
            versionLock: $this->createSuccessfulLock()
        )->execute());
    }

    public function testActivationCompletesPendingCacheCleanBeforeCheckingTarget(): void
    {
        $required = new State($this->physicalIndex(), null, CacheStatus::Required);
        $clean = new State($this->physicalIndex());
        $flag = $this->createMock(Flag::class);
        $flag->expects(self::exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls($required, $clean);
        $flag->expects(self::once())->method('save')->with($clean);
        $cacheClean = $this->createMock(CacheClean::class);
        $cacheClean->expects(self::once())->method('execute');

        self::assertFalse($this->createActivation(
            stateFlag: $flag,
            cacheClean: $cacheClean,
            versionLock: $this->createSuccessfulLock()
        )->execute());
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedFingerprintConfiguration(EmbeddedAttribute $attribute): array
    {
        return [
            'index_alias' => 'alias',
            'index_schema_version' => 1,
            'indexed_store_ids' => [1],
            'title_attribute_code' => 'name',
            'embedded_attributes' => [
                1 => [[
                    'attribute_code' => 'embedding_template',
                    'composite' => false,
                    'parsing_strategy' => 'text_as_is',
                    'template' => null,
                    'children' => [[
                        'attribute_code' => 'color',
                        'composite' => true,
                        'parsing_strategy' => 'html_to_text',
                        'template' => 'Color: {color}',
                        'children' => null,
                    ]],
                ]],
            ],
            'chunking' => [
                'max_tokens' => 100,
                'overlap_tokens' => 10,
                'estimated_characters_per_token' => 4,
            ],
            'embedding' => [
                'api_protocol' => 'openai',
                'model' => 'model',
                'vector_dimensions' => 3,
                'document_template' => '{text}',
            ],
            'index' => [
                'vector_method' => 'hnsw',
                'vector_engine' => 'lucene',
                'vector_space' => 'cosinesimil',
            ],
        ];
    }

    private function createConfigurationFingerprint(
        SerializerInterface $serializer,
        ?EmbeddedAttribute $attribute = null,
        ?EmbeddedAttributesConfig $attributesConfig = null,
        ?IndexingScopeConfig $scopeConfig = null
    ): ConfigurationFingerprint {
        $embedder = self::createStub(EmbedderConfig::class);
        $embedder->method('getMaximumChunkTokens')->willReturn(100);
        $embedder->method('getChunkOverlapTokens')->willReturn(10);
        $embedder->method('getEstimatedCharactersPerToken')->willReturn(4);
        $embedder->method('getEmbeddingApiProtocol')->willReturn('openai');
        $embedder->method('getEmbeddingModel')->willReturn('model');
        $embedder->method('getVectorDimensions')->willReturn(3);
        $embedder->method('getEmbedderDocumentTemplate')->willReturn('{text}');
        if ($attributesConfig === null) {
            $attributesConfig = self::createStub(EmbeddedAttributesConfig::class);
            $attributesConfig->method('isDocumentTitleEnabled')->willReturn(true);
            $attributesConfig->method('getDocumentTitleAttributeCode')->willReturn('name');
            $attributesConfig->method('getAttributes')
                ->willReturn($attribute === null ? [] : [$attribute]);
        }

        if ($scopeConfig === null) {
            $scopeConfig = self::createStub(IndexingScopeConfig::class);
            $scopeConfig->method('getStoreIdsForIndexing')->willReturn([1]);
        }
        $search = self::createStub(SearchConfig::class);
        $search->method('getIndexSchemaVersion')->willReturn(1);
        $search->method('getVectorMethod')->willReturn('hnsw');
        $search->method('getVectorEngine')->willReturn('lucene');
        $search->method('getVectorSpace')->willReturn('cosinesimil');
        $indexName = self::createStub(IndexName::class);
        $indexName->method('getAlias')->willReturn('alias');

        return new ConfigurationFingerprint(
            $embedder,
            $attributesConfig,
            $scopeConfig,
            $search,
            $indexName,
            $serializer
        );
    }

    private function physicalIndex(
        int $number = 1,
        string $name = 'alias_v1',
        string $fingerprint = 'fingerprint'
    ): PhysicalIndex {
        return new PhysicalIndex(
            $number,
            $name,
            $fingerprint,
            new QueryConfigurationSnapshot('model', 3, '{text}')
        );
    }

    /**
     * @return array<string, array<string, int|string>|int|string>
     */
    private static function staticPhysicalIndexData(): array
    {
        return [
            'number' => 1,
            'index_name' => 'alias_v1',
            'configuration_fingerprint' => 'fingerprint',
            'query_configuration' => [
                'embedding_model' => 'model',
                'vector_dimensions' => 3,
                'query_template' => '{text}',
            ],
        ];
    }

    private function createIndexerRegistry(IndexerInterface $indexer): IndexerRegistry
    {
        $registry = self::createStub(IndexerRegistry::class);
        $registry->method('get')->willReturn($indexer);

        return $registry;
    }

    private function createSuccessfulLock(): VersionLock
    {
        $lock = $this->createMock(VersionLock::class);
        $lock->method('lock')->willReturn(true);
        $lock->expects(self::once())->method('unlock');

        return $lock;
    }

    private function createPreparation(
        Flag $flag,
        OpenSearch $openSearch,
        VersionLock $versionLock
    ): Preparation {
        $fingerprint = self::createStub(ConfigurationFingerprint::class);
        $fingerprint->method('get')->willReturn('fingerprint');
        $embedder = self::createStub(EmbedderConfig::class);
        $embedder->method('getEmbeddingModel')->willReturn('model');
        $embedder->method('getVectorDimensions')->willReturn(3);
        $processing = self::createStub(SemanticDataProcessingConfig::class);
        $processing->method('getIndexerLockTimeoutSeconds')->willReturn(10);
        $result = self::createStub(SemanticSearchResultConfig::class);
        $result->method('getEmbedderQueryTemplate')->willReturn('{text}');
        $indexName = self::createStub(IndexName::class);
        $indexName->method('getVersionName')->willReturn('alias_v3');

        return new Preparation(
            $fingerprint,
            $embedder,
            $processing,
            $result,
            $indexName,
            $flag,
            $openSearch,
            $versionLock
        );
    }

    private function createActivation(
        ?ConfigurationFingerprint $configurationFingerprint = null,
        ?Flag $stateFlag = null,
        ?OpenSearch $openSearch = null,
        ?Readiness $readiness = null,
        ?CacheClean $cacheClean = null,
        ?VersionLock $versionLock = null
    ): Activation {
        return new Activation(
            $configurationFingerprint ?? self::createStub(ConfigurationFingerprint::class),
            $stateFlag ?? self::createStub(Flag::class),
            $openSearch ?? self::createStub(OpenSearch::class),
            $readiness ?? self::createStub(Readiness::class),
            $cacheClean ?? self::createStub(CacheClean::class),
            $versionLock ?? self::createStub(VersionLock::class)
        );
    }
}
