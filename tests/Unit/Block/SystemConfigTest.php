<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Block;

use ArrayIterator;
use DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field\AttributeConfiguration\Composition;
use DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field\AttributeConfiguration\ParsingStrategy;
use DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field\AttributeConfiguration\ProductAttribute;
use DavidBel\AiSearch\Block\Adminhtml\System\Config\SearchSource;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing\HtmlToText;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing\TextAsIs;
use DavidBel\AiSearch\Model\Config\Source\ProductAttribute as ProductAttributeSource;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use Magento\Backend\Block\Widget\Button;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;

class SystemConfigTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    public function testProductAttributeSourceLoadsLabelsSkipsEmptyCodeAndCaches(): void
    {
        $labeled = $this->getMockBuilder(Attribute::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAttributeCode'])
            ->getMock();
        $labeled->expects(self::atLeastOnce())->method('getAttributeCode')->willReturn('name');
        $labeled->setData('frontend_label', ' Name ');
        $unlabeled = $this->getMockBuilder(Attribute::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAttributeCode'])
            ->getMock();
        $unlabeled->expects(self::atLeastOnce())
            ->method('getAttributeCode')
            ->willReturn('description');
        $unlabeled->setData('frontend_label', null);
        $empty = $this->getMockBuilder(Attribute::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAttributeCode'])
            ->getMock();
        $empty->expects(self::atLeastOnce())->method('getAttributeCode')->willReturn('');
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::exactly(2))->method('setOrder')->willReturnSelf();
        $collection->method('getIterator')
            ->willReturn(new ArrayIterator([$labeled, $unlabeled, $empty]));
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($collection);
        $source = new ProductAttributeSource($factory);
        $expected = [
            ['value' => '', 'label' => '-- Select Attribute --'],
            ['value' => 'name', 'label' => 'Name (name)'],
            ['value' => 'description', 'label' => 'description'],
        ];

        self::assertSame($expected, $source->toOptionArray());
        self::assertSame($expected, $source->toOptionArray());
    }

    public function testCompositionRendererBuildsOptionsOnlyOnce(): void
    {
        $renderer = new TestableComposition($this->elementContext());
        $renderer->setInputName('composition')->setInputId('composition-id');

        $html = $renderer->renderSelect();
        self::assertStringContainsString('name="composition"', $html);
        self::assertStringContainsString('id="composition-id"', $html);
        self::assertStringContainsString('No: Current Product Only', $html);
        self::assertStringContainsString('Yes: Include Child Products', $html);
        self::assertSame($html, $renderer->renderSelect());
    }

    public function testParsingStrategyRendererBuildsAvailableStrategyOptions(): void
    {
        $renderer = new TestableParsingStrategy(
            $this->elementContext(),
            new Parsing([new TextAsIs(), new HtmlToText()])
        );
        $renderer->setInputName('strategy')->setInputId('strategy-id');

        $html = $renderer->renderSelect();
        self::assertStringContainsString('Select Parsing Strategy', $html);
        self::assertStringContainsString('Text As Is', $html);
        self::assertStringContainsString('Html To Text', $html);
        self::assertSame($html, $renderer->renderSelect());
    }

    public function testProductAttributeRendererSupportsSingleAndMultipleInputs(): void
    {
        $source = self::createStub(ProductAttributeSource::class);
        $source->method('toOptionArray')->willReturn([
            ['value' => '', 'label' => 'Select'],
            ['value' => 'name', 'label' => 'Name'],
        ]);
        $single = new TestableProductAttribute($this->elementContext(), $source);
        $single->setInputName('attribute')->setInputId('attribute-id');
        $singleHtml = $single->renderSelect();

        self::assertStringContainsString('name="attribute"', $singleHtml);
        self::assertStringContainsString('admin__control-select', $singleHtml);
        self::assertStringContainsString('value=""', $singleHtml);

        $multiple = new TestableProductAttribute(
            $this->elementContext(),
            $source,
            ['is_multiple' => true]
        );
        $multiple->setInputName('attributes')->setInputId('attributes-id');
        $multipleHtml = $multiple->renderSelect();
        self::assertStringContainsString('name="attributes[]"', $multipleHtml);
        self::assertStringContainsString('multiple="multiple" size="6"', $multipleHtml);
        self::assertStringNotContainsString('value=""', $multipleHtml);
    }

    public function testDocumentsFieldPreparesColumnsAndSelectedValues(): void
    {
        $renderers = $this->renderers();
        $layout = $this->rendererLayout($renderers, false);
        $block = $this->getMockBuilder(TestableDocuments::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getLayout', 'addColumn'])
            ->getMock();
        $block->method('getLayout')->willReturn($layout);
        $block->expects(self::exactly(3))->method('addColumn');

        $block->prepareToRender();
        $row = new DataObject([
            'attribute_code' => 'name',
            'composite' => true,
            'parsing_strategy' => 'text_as_is',
        ]);
        $block->prepareArrayRow($row);

        self::assertFalse($block->addsAfter());
        self::assertSame('Add Document', $block->buttonLabel());
        self::assertSame(
            [
                'option_attribute-name' => 'selected="selected"',
                'option_composition-1' => 'selected="selected"',
                'option_parsing-text_as_is' => 'selected="selected"',
            ],
            $row->getData('option_extra_attrs')
        );
    }

    public function testDynamicDocumentFieldNormalizesMultipleAttributeCodes(): void
    {
        $renderers = $this->renderers();
        $layout = $this->rendererLayout($renderers, true);
        $block = $this->getMockBuilder(TestableDynamicDocument::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getLayout', 'addColumn'])
            ->getMock();
        $block->method('getLayout')->willReturn($layout);
        $block->expects(self::exactly(4))->method('addColumn');

        $block->prepareToRender();
        $row = new DataObject([
            'attribute_code' => [' name ', '', 10, 'color'],
            'composite' => 1,
            'parsing_strategy' => 'text_as_is',
        ]);
        $block->prepareArrayRow($row);

        self::assertFalse($block->addsAfter());
        self::assertSame('Add Document Part', $block->buttonLabel());
        self::assertSame(
            [
                'option_attribute-name' => 'selected="selected"',
                'option_attribute-color' => 'selected="selected"',
                'option_composition-1' => 'selected="selected"',
                'option_parsing-text_as_is' => 'selected="selected"',
            ],
            $row->getData('option_extra_attrs')
        );
    }

    public function testDynamicDocumentFieldSupportsCommaSeparatedAndInvalidValues(): void
    {
        $renderers = $this->renderers();
        $layout = $this->rendererLayout($renderers, true);
        $block = $this->getMockBuilder(TestableDynamicDocument::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getLayout'])
            ->getMock();
        $block->expects(self::exactly(3))->method('getLayout')->willReturn($layout);
        $stringRow = new DataObject([
            'attribute_code' => 'name, color, ',
            'composite' => [],
            'parsing_strategy' => null,
        ]);
        $block->prepareArrayRow($stringRow);
        $optionAttributes = $stringRow->getData('option_extra_attrs');
        self::assertIsArray($optionAttributes);
        self::assertArrayHasKey(
            'option_attribute-color',
            $optionAttributes
        );

        $invalidRow = new DataObject(['attribute_code' => 10]);
        $block->prepareArrayRow($invalidRow);
        self::assertSame(
            [
                'option_composition-' => 'selected="selected"',
                'option_parsing-' => 'selected="selected"',
            ],
            $invalidRow->getData('option_extra_attrs')
        );
    }

    public function testSearchSourcePrependsWarningToFormHtml(): void
    {
        $form = self::createStub(Form::class);
        $form->method('getHtml')->willReturn('<form>settings</form>');
        $block = $this->getMockBuilder(SearchSource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getForm', 'escapeHtml'])
            ->getMock();
        $block->expects(self::atLeastOnce())->method('getForm')->willReturn($form);
        $block->expects(self::exactly(2))->method('escapeHtml')->willReturnArgument(0);

        $html = $block->getFormHtml();
        self::assertStringContainsString('message-warning', $html);
        self::assertStringContainsString('Important:', $html);
        self::assertStringEndsWith('<form>settings</form>', $html);
    }

    public function testConnectionButtonBuildsMageInitConfiguration(): void
    {
        $button = self::createStub(Button::class);
        $button->method('toHtml')->willReturn('<button>Test</button>');
        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects(self::once())->method('createBlock')->willReturn($button);
        $field = $this->getMockBuilder(TestableConnectionField::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getLayout', 'getUrl'])
            ->getMock();
        $field->expects(self::once())->method('getLayout')->willReturn($layout);
        $field->expects(self::once())->method('getUrl')->willReturn('connection-url');
        $element = self::createStub(AbstractElement::class);
        $element->method('getHtmlId')->willReturn('button-id');

        self::assertSame('<button>Test</button>', $field->elementHtml($element));
    }

    private function elementContext(): Context
    {
        $context = self::createStub(Context::class);
        $context->method('getEscaper')->willReturn(new Escaper());

        return $context;
    }

    /**
     * @return array{ProductAttribute, Composition, ParsingStrategy}
     */
    private function renderers(): array
    {
        $attribute = self::createStub(ProductAttribute::class);
        $attribute->method('calcOptionHash')->willReturnMap([
            ['name', 'attribute-name'],
            ['color', 'attribute-color'],
        ]);
        $composition = self::createStub(Composition::class);
        $composition->method('calcOptionHash')->willReturnMap([
            ['1', 'composition-1'],
            ['', 'composition-'],
        ]);
        $parsing = self::createStub(ParsingStrategy::class);
        $parsing->method('calcOptionHash')->willReturnMap([
            ['text_as_is', 'parsing-text_as_is'],
            ['', 'parsing-'],
        ]);

        return [$attribute, $composition, $parsing];
    }

    /**
     * @param array{ProductAttribute, Composition, ParsingStrategy} $renderers
     */
    private function rendererLayout(array $renderers, bool $multiple): LayoutInterface
    {
        [$attribute, $composition, $parsing] = $renderers;
        $attributeData = ['data' => ['is_render_to_js_template' => true]];

        if ($multiple) {
            $attributeData['data']['is_multiple'] = true;
        }

        $layout = self::createStub(LayoutInterface::class);
        $layout->method('createBlock')->willReturnMap([
            [ProductAttribute::class, '', $attributeData, $attribute],
            [
                Composition::class,
                '',
                ['data' => ['is_render_to_js_template' => true]],
                $composition,
            ],
            [
                ParsingStrategy::class,
                '',
                ['data' => ['is_render_to_js_template' => true]],
                $parsing,
            ],
        ]);

        return $layout;
    }
}
