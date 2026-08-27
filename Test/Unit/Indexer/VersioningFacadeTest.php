<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Indexer;

use DavidBel\AiSearch\Indexer\Versioning as VersioningFacade;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex\QueryConfigurationSnapshot;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndexDelete;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndexDeleteFactory;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndexProvider;
use DavidBel\AiSearch\Indexer\Versioning\ProductIndexerInvalidation;
use DavidBel\AiSearch\Indexer\Versioning\ProductIndexerInvalidationFactory;
use DavidBel\AiSearch\Indexer\Versioning\Target\Activation;
use DavidBel\AiSearch\Indexer\Versioning\Target\ActivationFactory;
use DavidBel\AiSearch\Indexer\Versioning\Target\Preparation;
use DavidBel\AiSearch\Indexer\Versioning\Target\PreparationFactory;
use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class VersioningFacadeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            PreparationFactory::class,
            ActivationFactory::class,
            ProductIndexerInvalidationFactory::class,
            PhysicalIndexDeleteFactory::class
        );
    }

    public function testDelegatesVersioningOperations(): void
    {
        $preparation = $this->createMock(Preparation::class);
        $preparation->expects(self::once())->method('prepare');
        $preparation->expects(self::once())->method('markDocumentProcessingComplete');
        $activation = $this->createMock(Activation::class);
        $activation->expects(self::once())->method('execute')->willReturn(true);
        $invalidation = $this->createMock(ProductIndexerInvalidation::class);
        $invalidation->expects(self::once())->method('execute');
        $delete = $this->createMock(PhysicalIndexDelete::class);
        $delete->expects(self::once())->method('execute')->willReturn(2);
        $target = $this->physicalIndex(2, 'target');
        $ingestion = $this->physicalIndex(3, 'ingestion');
        $search = $this->physicalIndex(4, 'search');
        $provider = $this->createMock(PhysicalIndexProvider::class);
        $provider->expects(self::exactly(2))->method('getTarget')->willReturn($target);
        $provider->expects(self::exactly(2))->method('getForIngestion')->willReturn($ingestion);
        $provider->expects(self::once())->method('getTargetForCurrentConfiguration')->willReturn(null);
        $provider->expects(self::once())->method('getActiveForCurrentConfiguration')->willReturn($search);
        $provider->expects(self::once())->method('getForSearch')->with(true)->willReturn($search);
        $versioning = new VersioningFacade(
            $this->preparationFactory($preparation),
            $this->activationFactory($activation),
            $this->invalidationFactory($invalidation),
            $provider,
            $this->deleteFactory($delete)
        );

        $versioning->prepareTargetForFullReindex();
        $versioning->markTargetDocumentProcessingComplete();
        self::assertSame(2, $versioning->getTargetIndexVersion());
        self::assertSame(3, $versioning->getIngestionIndexVersion());
        self::assertTrue($versioning->hasTargetIndexVersion());
        self::assertTrue($versioning->hasIngestionIndexVersion());
        $versioning->invalidateProductIndexerWhenNeeded();
        self::assertTrue($versioning->hasTargetOrActiveForCurrentConfiguration());
        self::assertSame($search, $versioning->getSearchIndex(true));
        self::assertTrue($versioning->activateTargetWhenReady());
        self::assertSame(2, $versioning->deleteObsoletePhysicalIndexes());
    }

    public function testReportsNoCurrentIndexAndMissingTarget(): void
    {
        $provider = self::createStub(PhysicalIndexProvider::class);
        $versioning = $this->createVersioning($provider);

        self::assertFalse($versioning->hasTargetIndexVersion());
        self::assertFalse($versioning->hasIngestionIndexVersion());
        self::assertFalse($versioning->hasTargetOrActiveForCurrentConfiguration());
        self::assertNull($versioning->getSearchIndex(false));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('target search index version is not available');
        $versioning->getTargetIndexVersion();
    }

    public function testRejectsMissingIngestionIndex(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ingestion search index version is not available');

        $this->createVersioning(self::createStub(PhysicalIndexProvider::class))
            ->getIngestionIndexVersion();
    }

    private function createVersioning(PhysicalIndexProvider $provider): VersioningFacade
    {
        return new VersioningFacade(
            $this->preparationFactory(self::createStub(Preparation::class)),
            $this->activationFactory(self::createStub(Activation::class)),
            $this->invalidationFactory(self::createStub(ProductIndexerInvalidation::class)),
            $provider,
            $this->deleteFactory(self::createStub(PhysicalIndexDelete::class))
        );
    }

    private function preparationFactory(Preparation $instance): PreparationFactory
    {
        $factory = self::createStub(PreparationFactory::class);
        $factory->method('create')->willReturn($instance);

        return $factory;
    }

    private function activationFactory(Activation $instance): ActivationFactory
    {
        $factory = self::createStub(ActivationFactory::class);
        $factory->method('create')->willReturn($instance);

        return $factory;
    }

    private function invalidationFactory(
        ProductIndexerInvalidation $instance
    ): ProductIndexerInvalidationFactory {
        $factory = self::createStub(ProductIndexerInvalidationFactory::class);
        $factory->method('create')->willReturn($instance);

        return $factory;
    }

    private function deleteFactory(PhysicalIndexDelete $instance): PhysicalIndexDeleteFactory
    {
        $factory = self::createStub(PhysicalIndexDeleteFactory::class);
        $factory->method('create')->willReturn($instance);

        return $factory;
    }

    private function physicalIndex(int $number, string $name): PhysicalIndex
    {
        return new PhysicalIndex(
            $number,
            $name,
            'fingerprint',
            new QueryConfigurationSnapshot('model', 3, '{text}')
        );
    }
}
