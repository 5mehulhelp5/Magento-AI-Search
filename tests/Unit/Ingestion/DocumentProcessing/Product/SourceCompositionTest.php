<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\DocumentProcessing\Product;

use DavidBel\AiSearch\Config\EmbeddedAttribute;
use DavidBel\AiSearch\Config\IndexingScopeConfig;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing\TextAsIs;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\DirectSourceBuilder;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\Eligibility;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\Eligibility\DataProvider;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\Eligibility\EligibleScope;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\EmbeddingTemplate;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\EmbeddingTemplate\AttributeData;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\EmbeddingTemplate\AttributeDataProvider;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\EmbeddingTemplate\AttributeValueResolver;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\EmbeddingTemplate\TemplateRenderer;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\EmbeddingTemplate\ValueFormatter;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\TitleResolver;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use PHPUnit\Framework\Attributes\DataProvider as PHPUnitDataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SourceCompositionTest extends TestCase
{
    public function testEligibilityReturnsEarlyWithoutProducts(): void
    {
        $dataProvider = $this->createMock(DataProvider::class);
        $dataProvider->expects(self::never())->method('getEligibleScopeRows');

        self::assertSame([], $this->createEligibility($dataProvider, [1])->getEligibleScopesByProductIds([]));
    }

    public function testEligibilityReturnsEarlyWithoutConfiguredStores(): void
    {
        $dataProvider = $this->createMock(DataProvider::class);
        $dataProvider->expects(self::never())->method('getEligibleScopeRows');

        self::assertSame([], $this->createEligibility($dataProvider, [])->getEligibleScopesByProductIds([10]));
    }

    public function testEligibilityBuildsSimpleAndCompositeScopes(): void
    {
        $dataProvider = $this->createMock(DataProvider::class);
        $dataProvider->expects(self::once())
            ->method('getEligibleScopeRows')
            ->with([10, 20, 30], [1, 2])
            ->willReturn([
                ['product_id' => '10', 'store_id' => '1', 'type_id' => 'simple'],
                ['product_id' => 20, 'store_id' => 2, 'type_id' => Configurable::TYPE_CODE],
                ['product_id' => 30, 'store_id' => 1, 'type_id' => Configurable::TYPE_CODE],
            ]);
        $dataProvider->expects(self::once())
            ->method('getChildIdsByParentId')
            ->with([10, 20, 30])
            ->willReturn([20 => [21, 22], 30 => [31]]);
        $dataProvider->expects(self::once())
            ->method('getEnabledChildIdsByStoreId')
            ->willReturn([2 => [22 => true]]);

        self::assertEquals(
            [
                10 => [new EligibleScope(1, [10])],
                20 => [new EligibleScope(2, [20, 22])],
            ],
            $this->createEligibility($dataProvider, [1, 2])
                ->getEligibleScopesByProductIds([10, 20, 30])
        );
    }

    public function testEligibilityReturnsEarlyWithoutScopeRows(): void
    {
        $dataProvider = $this->createMock(DataProvider::class);
        $dataProvider->method('getEligibleScopeRows')->willReturn([]);
        $dataProvider->expects(self::never())->method('getChildIdsByParentId');

        self::assertSame([], $this->createEligibility($dataProvider, [1])->getEligibleScopesByProductIds([10]));
    }

    /**
     * @param array<array-key, mixed> $row
     */
    #[PHPUnitDataProvider('invalidEligibilityRows')]
    public function testEligibilityRejectsInvalidRows(array $row, string $message): void
    {
        $dataProvider = self::createStub(DataProvider::class);
        $dataProvider->method('getEligibleScopeRows')->willReturn([$row]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        $this->createEligibility($dataProvider, [1])->getEligibleScopesByProductIds([10]);
    }

    /**
     * @return array<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidEligibilityRows(): array
    {
        return [
            'product id' => [
                ['product_id' => 0, 'store_id' => 1, 'type_id' => 'simple'],
                'product_id',
            ],
            'store id' => [
                ['product_id' => 10, 'store_id' => 0, 'type_id' => 'simple'],
                'store_id',
            ],
            'type' => [
                ['product_id' => 10, 'store_id' => 1, 'type_id' => null],
                'product type is invalid',
            ],
        ];
    }

    public function testEligibilityAddsParentIdsAndSortsAffectedProducts(): void
    {
        $dataProvider = $this->createMock(DataProvider::class);
        $dataProvider->expects(self::once())
            ->method('getParentIdsByChildIds')
            ->with([20, 10])
            ->willReturn([30, 10]);
        $eligibility = $this->createEligibility($dataProvider, [1]);

        self::assertSame([10, 20, 30], $eligibility->getAffectedProductIds([20, 10]));
        self::assertSame([], $eligibility->getAffectedProductIds([]));
    }

    public function testDirectSourceBuilderResolvesScopesFallbacksAndDuplicates(): void
    {
        $attribute = new EmbeddedAttribute('description', true, 'text_as_is', null, null);
        $scopes = [10 => [new EligibleScope(1, [10, 11, 12, 13])]];
        $values = [
            'description' => [
                '10:0' => 'Default',
                '11:1' => 'Store',
                '12:1' => 'Store',
                '13:1' => '',
            ],
        ];
        $sources = (new DirectSourceBuilder(new TitleResolver()))->buildSourcesByProductId(
            [$attribute],
            [10, 20],
            $scopes,
            $values,
            ['10:1' => 'Product title']
        );

        self::assertSame('Default' . "\n\n" . 'Store', $sources[10][0]->storeScopedSources[0]->content);
        self::assertSame('Product title', $sources[10][0]->storeScopedSources[0]->title);
        self::assertSame([], $sources[20][0]->storeScopedSources);
    }

    public function testDirectSourceBuilderUsesOnlyParentForNonCompositeAttribute(): void
    {
        $attribute = new EmbeddedAttribute('name', false, 'text_as_is', null, null);
        $sources = (new DirectSourceBuilder(new TitleResolver()))->buildSourcesByProductId(
            [$attribute],
            [10],
            [10 => [new EligibleScope(2, [10, 11])]],
            ['name' => ['10:0' => 'Parent', '11:2' => 'Child']],
            []
        );

        self::assertSame('Parent', $sources[10][0]->storeScopedSources[0]->content);
        self::assertNull($sources[10][0]->storeScopedSources[0]->title);
    }

    public function testTitleResolverUsesStoreThenDefaultAndRejectsBlankTitles(): void
    {
        $resolver = new TitleResolver();
        $values = ['10:0' => 'Default', '10:1' => 'Store', '20:0' => '   '];

        self::assertSame('Store', $resolver->getTitle($values, 10, 1));
        self::assertSame('Default', $resolver->getTitle($values, 10, 2));
        self::assertNull($resolver->getTitle($values, 20, 2));
        self::assertNull($resolver->getTitle($values, 30, 1));
    }

    public function testValueFormatterHandlesEmptyUniqueAndNaturalLists(): void
    {
        $formatter = new ValueFormatter();

        self::assertSame('', $formatter->format(['', '   ']));
        self::assertSame('one', $formatter->format([' one ', 'one']));
        self::assertSame('one and two', $formatter->format(['one', 'two']));
        self::assertSame('one, two, and three', $formatter->format(['one', 'two', 'three']));
    }

    public function testAttributeValueResolverUsesScopedValuesAndOptionLabels(): void
    {
        $data = new AttributeData(
            [
                1 => ['code' => 'name', 'backend_type' => 'varchar', 'frontend_input' => 'text'],
                2 => ['code' => 'color', 'backend_type' => 'int', 'frontend_input' => 'select'],
                3 => ['code' => 'sizes', 'backend_type' => 'varchar', 'frontend_input' => 'multiselect'],
            ],
            [
                'name' => ['10:0' => 'Default', '10:2' => 'Store', '20:0' => ''],
                'color' => ['10:0' => '5'],
                'sizes' => ['10:0' => '0, 6, ,7'],
            ],
            [5 => [0 => 'Red', 2 => 'Crimson'], 6 => [0 => 'Small'], 7 => [0 => 'Large']]
        );
        $provider = self::createStub(AttributeDataProvider::class);
        $provider->method('get')->willReturn($data);
        $resolver = new AttributeValueResolver($provider);

        self::assertSame(
            [
                'name' => ['10:1' => ['Default'], '10:2' => ['Store']],
                'color' => ['10:1' => ['Red'], '10:2' => ['Crimson']],
                'sizes' => ['10:1' => ['Small', 'Large'], '10:2' => ['Small', 'Large']],
                'missing' => [],
            ],
            $resolver->getValuesByAttributeCode(
                ['name', 'color', 'sizes', 'missing'],
                [10, 20],
                [1, 2]
            )
        );
    }

    public function testAttributeValueResolverReturnsEarlyForEmptyInputs(): void
    {
        $provider = $this->createMock(AttributeDataProvider::class);
        $provider->expects(self::never())->method('get');
        $resolver = new AttributeValueResolver($provider);

        self::assertSame([], $resolver->getValuesByAttributeCode([], [1], [1]));
        self::assertSame([], $resolver->getValuesByAttributeCode(['name'], [], [1]));
        self::assertSame([], $resolver->getValuesByAttributeCode(['name'], [1], []));
    }

    public function testAttributeValueResolverSkipsEmptyResolvedOptionLists(): void
    {
        $data = new AttributeData(
            [1 => ['code' => 'sizes', 'backend_type' => 'varchar', 'frontend_input' => 'multiselect']],
            ['sizes' => ['10:0' => '0, ,']],
            []
        );
        $provider = self::createStub(AttributeDataProvider::class);
        $provider->method('get')->willReturn($data);

        self::assertSame(
            ['sizes' => []],
            (new AttributeValueResolver($provider))->getValuesByAttributeCode(['sizes'], [10], [1])
        );
    }

    #[PHPUnitDataProvider('invalidOptionValues')]
    public function testAttributeValueResolverRejectsInvalidOptions(
        string $rawValue,
        string $message
    ): void {
        $data = new AttributeData(
            [1 => ['code' => 'color', 'backend_type' => 'int', 'frontend_input' => 'select']],
            ['color' => ['10:0' => $rawValue]],
            []
        );
        $provider = self::createStub(AttributeDataProvider::class);
        $provider->method('get')->willReturn($data);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new AttributeValueResolver($provider))->getValuesByAttributeCode(['color'], [10], [1]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidOptionValues(): array
    {
        return [
            'invalid id' => ['invalid', 'option_id'],
            'missing label' => ['5', 'option label could not be resolved'],
        ];
    }

    public function testTemplateRendererFindsAttributesAndRendersCompositeValues(): void
    {
        $renderer = $this->createTemplateRenderer();
        $template = $this->template([
            $this->fragment('name', false, 'Name: {name}'),
            $this->fragment('color, name, color', true, '{color} {name}'),
        ]);
        $values = [
            'name' => ['10:1' => ['Parent'], '11:1' => ['Child']],
            'color' => ['10:1' => ['Red'], '11:1' => ['Blue']],
        ];

        self::assertSame(['name', 'color'], $renderer->getAttributeCodes([$template]));
        self::assertSame(
            'Name: Parent Red and Blue Parent and Child',
            $renderer->render($template, 10, new EligibleScope(1, [10, 11]), $values)
        );
    }

    public function testTemplateRendererSkipsFragmentWithoutValues(): void
    {
        $template = $this->template([
            $this->fragment('missing', false, 'Missing: {missing}'),
            $this->fragment('name', false, 'Name: {name}'),
        ]);

        self::assertSame(
            'Name: Product',
            $this->createTemplateRenderer()->render(
                $template,
                10,
                new EligibleScope(1, [10]),
                ['name' => ['10:1' => ['Product']]]
            )
        );
    }

    #[PHPUnitDataProvider('invalidTemplates')]
    public function testTemplateRendererRejectsInvalidTemplates(
        EmbeddedAttribute $template,
        string $message
    ): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        $this->createTemplateRenderer()->render(
            $template,
            10,
            new EligibleScope(1, [10]),
            ['name' => ['10:1' => ['Product']]]
        );
    }

    /**
     * @return array<string, array{EmbeddedAttribute, string}>
     */
    public static function invalidTemplates(): array
    {
        return [
            'no fragments' => [self::staticTemplate([]), 'at least one fragment'],
            'no text' => [self::staticTemplate([self::staticFragment('name', '')]), 'must contain text'],
            'empty code' => [self::staticTemplate([self::staticFragment('name,', '{name}')]), 'must not be empty'],
            'placeholder' => [self::staticTemplate([self::staticFragment('name', 'text')]), 'missing a configured'],
        ];
    }

    public function testEmbeddingTemplateBuildsOneDocumentAcrossEligibleStores(): void
    {
        $dynamicDocument = $this->template([$this->fragment('name', false, 'Name: {name}')]);
        $data = new AttributeData(
            [1 => ['code' => 'name', 'backend_type' => 'varchar', 'frontend_input' => 'text']],
            ['name' => ['10:0' => 'Default', '10:2' => 'Store']],
            []
        );
        $provider = self::createStub(AttributeDataProvider::class);
        $provider->method('get')->willReturn($data);
        $renderer = new TemplateRenderer(
            new ValueFormatter(),
            new Parsing([new TextAsIs()])
        );
        $builder = new EmbeddingTemplate(
            new AttributeValueResolver($provider),
            $renderer,
            new TitleResolver()
        );

        $sources = $builder->buildSourcesByProductId(
            [1 => $dynamicDocument, 2 => $dynamicDocument],
            [10, 20],
            [10 => [new EligibleScope(1, [10]), new EligibleScope(2, [10])]],
            ['10:0' => 'Title']
        );

        self::assertCount(1, $sources[10]);
        self::assertSame('embedding_template', $sources[10][0]->sourceCode);
        self::assertSame('Name: Default', $sources[10][0]->storeScopedSources[0]->content);
        self::assertSame('Name: Store', $sources[10][0]->storeScopedSources[1]->content);
        self::assertSame('Title', $sources[10][0]->storeScopedSources[1]->title);
        self::assertSame([], $sources[20]);
    }

    public function testEmbeddingTemplateSkipsScopesWithoutDynamicDocument(): void
    {
        $resolver = self::createStub(AttributeValueResolver::class);
        $resolver->method('getValuesByAttributeCode')->willReturn([]);
        $renderer = self::createStub(TemplateRenderer::class);
        $renderer->method('getAttributeCodes')->willReturn([]);
        $builder = new EmbeddingTemplate($resolver, $renderer, new TitleResolver());

        self::assertSame(
            [10 => []],
            $builder->buildSourcesByProductId(
                [],
                [10],
                [10 => [new EligibleScope(1, [10])]],
                []
            )
        );
    }

    /**
     * @param list<int> $storeIds
     */
    private function createEligibility(DataProvider $dataProvider, array $storeIds): Eligibility
    {
        $config = self::createStub(IndexingScopeConfig::class);
        $config->method('getStoreIdsForIndexing')->willReturn($storeIds);

        return new Eligibility($dataProvider, $config);
    }

    private function createTemplateRenderer(): TemplateRenderer
    {
        return new TemplateRenderer(
            new ValueFormatter(),
            new Parsing([new TextAsIs()])
        );
    }

    /**
     * @param list<EmbeddedAttribute> $fragments
     */
    private function template(array $fragments): EmbeddedAttribute
    {
        return self::staticTemplate($fragments);
    }

    private function fragment(
        string $code,
        bool $composite,
        string $template
    ): EmbeddedAttribute {
        return new EmbeddedAttribute($code, $composite, 'text_as_is', $template, null);
    }

    /**
     * @param list<EmbeddedAttribute> $fragments
     */
    private static function staticTemplate(array $fragments): EmbeddedAttribute
    {
        return new EmbeddedAttribute('embedding_template', false, 'text_as_is', null, $fragments);
    }

    private static function staticFragment(string $code, string $template): EmbeddedAttribute
    {
        return new EmbeddedAttribute($code, false, 'text_as_is', $template, null);
    }
}
