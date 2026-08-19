<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Config;

use DavidBel\AiSearch\Config\EmbeddedAttribute;
use DavidBel\AiSearch\Config\EmbeddedAttributesConfig;
use DavidBel\AiSearch\Config\EmbeddedAttributesConfig\Documents;
use DavidBel\AiSearch\Config\EmbeddedAttributesConfig\DynamicDocument;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class EmbeddedAttributesConfigTest extends TestCase
{
    public function testCombinesDocumentsWithTheStoreScopedDynamicDocument(): void
    {
        $document = new EmbeddedAttribute('description', false, 'text_as_is', null, null);
        $dynamicDocument = new EmbeddedAttribute(
            'embedding_template',
            false,
            'text_as_is',
            null,
            []
        );
        $documents = $this->createMock(Documents::class);
        $documents->expects(self::once())->method('get')->willReturn([$document]);
        $dynamic = $this->createMock(DynamicDocument::class);
        $dynamic->expects(self::once())
            ->method('get')
            ->with(3)
            ->willReturn($dynamicDocument);

        self::assertSame(
            [$document, $dynamicDocument],
            (new EmbeddedAttributesConfig(
                self::createStub(ScopeConfigInterface::class),
                $documents,
                $dynamic
            ))->getAttributes(3)
        );
    }

    public function testReturnsDocumentsWithoutADisabledDynamicDocument(): void
    {
        $document = new EmbeddedAttribute('description', false, 'text_as_is', null, null);
        $documents = self::createStub(Documents::class);
        $documents->method('get')->willReturn([$document]);
        $dynamic = self::createStub(DynamicDocument::class);
        $dynamic->method('get')->willReturn(null);

        self::assertSame(
            [$document],
            (new EmbeddedAttributesConfig(
                self::createStub(ScopeConfigInterface::class),
                $documents,
                $dynamic
            ))->getAttributes(3)
        );
    }
}
