<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Ingestion\DocumentProcessing;

use DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing\HtmlToText;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing\ParsingInterface;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing\TextAsIs;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ParsingTest extends TestCase
{
    public function testKeepsPlainTextAndTrimsItsEdges(): void
    {
        $parser = new TextAsIs();

        self::assertSame('text_as_is', $parser->getCode());
        self::assertSame("First\nsecond", $parser->parse("  First\nsecond  "));
    }

    public function testConvertsHtmlToNormalizedText(): void
    {
        $parser = new HtmlToText();
        $html = '<style>ignored</style><h1>Title</h1><p>First&nbsp; line<br>Second</p>'
            . '<script>ignored</script><ul><li>One</li><li>Two</li></ul>';

        self::assertSame('html_to_text', $parser->getCode());
        self::assertSame("Title\n\nFirst line\nSecond\n\nOne\nTwo", $parser->parse($html));
    }

    public function testReturnsAnEmptyStringForEmptyHtml(): void
    {
        self::assertSame('', (new HtmlToText())->parse(''));
    }

    public function testIgnoresHtmlComments(): void
    {
        self::assertSame('Visible', (new HtmlToText())->parse('<!-- hidden --><p>Visible</p>'));
    }

    public function testParsingRegistryListsAndDelegatesToStrategies(): void
    {
        $strategy = $this->createMock(ParsingInterface::class);
        $strategy->expects(self::atLeastOnce())->method('getCode')->willReturn('custom');
        $strategy->expects(self::once())->method('parse')->with('input')->willReturn('output');
        $parsing = new Parsing([$strategy]);

        self::assertSame([$strategy], $parsing->getAvailableStrategies());
        self::assertSame('output', $parsing->parse('input', 'custom'));
    }

    public function testParsingRegistryRequiresAtLeastOneStrategy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one parsing strategy is required');

        new Parsing([]);
    }

    public function testParsingRegistryRejectsEmptyAndDuplicateCodes(): void
    {
        $empty = self::createStub(ParsingInterface::class);
        $empty->method('getCode')->willReturn('  ');

        try {
            new Parsing([$empty]);
            self::fail('An empty strategy code must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('code cannot be empty', $exception->getMessage());
        }

        $first = self::createStub(ParsingInterface::class);
        $first->method('getCode')->willReturn('duplicate');
        $second = self::createStub(ParsingInterface::class);
        $second->method('getCode')->willReturn('duplicate');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('configured more than once');
        new Parsing([$first, $second]);
    }

    public function testParsingRegistryRejectsUnknownStrategy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not configured');

        (new Parsing([new TextAsIs()]))->parse('input', 'missing');
    }
}
