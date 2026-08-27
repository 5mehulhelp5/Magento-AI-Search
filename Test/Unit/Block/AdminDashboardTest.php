<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Block;

use DavidBel\AiSearch\Block\Adminhtml\DashboardButton;
use DavidBel\AiSearch\Block\Adminhtml\Test\SemanticSearchButton;
use DavidBel\AiSearch\Test\Unit\TestDouble\ObjectManagerStub;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Renderer\Element as ElementRenderer;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset as FieldsetRenderer;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset\Element as FieldsetElementRenderer;
use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\CollectionFactory;
use Magento\Framework\Data\Form\Element\Factory;
use Magento\Framework\Data\Form\Element\Text;
use Magento\Framework\Data\Form;
use Magento\Framework\Escaper;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\Math\Random;
use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class AdminDashboardTest extends TestCase
{
    private Random $random;

    private SecureHtmlRenderer $secureHtmlRenderer;

    protected function setUp(): void
    {
        $this->random = self::createStub(Random::class);
        $this->random->method('getRandomString')->willReturn('random');
        $this->secureHtmlRenderer = self::createStub(SecureHtmlRenderer::class);
        ObjectManager::setInstance(new ObjectManagerStub([
            JsonHelper::class => self::createStub(JsonHelper::class),
            DirectoryHelper::class => self::createStub(DirectoryHelper::class),
            Random::class => $this->random,
            SecureHtmlRenderer::class => $this->secureHtmlRenderer,
        ]));
    }

    public function testDashboardButtonUsesDefaultClassAndDashboardUrl(): void
    {
        $backendUrl = self::createStub(BackendUrlInterface::class);
        $backendUrl->method('getUrl')->willReturn('dashboard-url');
        $button = new DashboardButton(
            $this->context(),
            $backendUrl,
            $this->random,
            $this->secureHtmlRenderer
        );

        $label = $button->getLabel();
        self::assertInstanceOf(Phrase::class, $label);
        self::assertSame('AI Search Dashboard', (string) $label);
        self::assertSame('primary', $button->getClass());
        self::assertSame("location.href = 'dashboard-url';", $button->getOnClick());
    }

    public function testDashboardButtonPreservesConfiguredClass(): void
    {
        $backendUrl = self::createStub(BackendUrlInterface::class);
        $backendUrl->method('getUrl')->willReturn('dashboard-url');
        $button = new DashboardButton(
            $this->context(),
            $backendUrl,
            $this->random,
            $this->secureHtmlRenderer,
            ['class' => 'secondary']
        );

        self::assertSame('secondary', $button->getClass());
    }

    public function testSemanticSearchButtonBuildsStoreConfiguration(): void
    {
        $admin = $this->store(0, Store::ADMIN_CODE, 'Admin', true);
        $active = $this->store(2, 'english', 'English', true);
        $disabled = $this->store(3, 'german', 'German', false);
        $repository = self::createStub(StoreRepositoryInterface::class);
        $repository->method('getList')->willReturn([$admin, $active, $disabled]);
        $storeManager = self::createStub(StoreManagerInterface::class);
        $storeManager->method('getDefaultStoreView')->willReturn($active);
        $button = new TestableSemanticSearchButton(
            $this->context('semantic-url'),
            $repository,
            $storeManager
        );

        self::assertSame($button, $button->prepare());
        $label = $button->getLabel();
        self::assertInstanceOf(Phrase::class, $label);
        self::assertSame('Test Semantic Search', (string) $label);
        self::assertSame(
            [
                'mage-init' => [
                    'DavidBel_AiSearch/js/test-semantic-search' => [
                        'url' => 'semantic-url',
                        'stores' => [
                            ['id' => '2', 'label' => 'English (english)'],
                            ['id' => '3', 'label' => 'German (german) [Disabled]'],
                        ],
                        'selectedStoreId' => '2',
                    ],
                ],
            ],
            $button->getData('data_attribute')
        );
    }

    public function testSemanticSearchButtonHandlesMissingDefaultStore(): void
    {
        $repository = self::createStub(StoreRepositoryInterface::class);
        $repository->method('getList')->willReturn([]);
        $storeManager = self::createStub(StoreManagerInterface::class);
        $storeManager->method('getDefaultStoreView')->willReturn(null);
        $button = new TestableSemanticSearchButton(
            $this->context('semantic-url'),
            $repository,
            $storeManager
        );

        $button->prepare();
        /** @var array{mage-init: array<string, array{selectedStoreId: string}>} $configuration */
        $configuration = $button->getData('data_attribute');

        self::assertSame(
            '',
            $configuration['mage-init']['DavidBel_AiSearch/js/test-semantic-search']
                ['selectedStoreId']
        );
    }

    public function testEmbedderConnectionButtonUsesConfiguredAndDefaultUrls(): void
    {
        $configured = new TestableEmbedderConnectionButton(
            $this->context('default-url'),
            ['url' => 'configured-url'],
            $this->random,
            $this->secureHtmlRenderer
        );
        $default = new TestableEmbedderConnectionButton(
            $this->context('default-url'),
            [],
            $this->random,
            $this->secureHtmlRenderer
        );

        self::assertSame($configured, $configured->prepare());
        self::assertSame($default, $default->prepare());
        $label = $configured->getLabel();
        self::assertInstanceOf(Phrase::class, $label);
        self::assertSame('Test Connection', (string) $label);
        /** @var array{mage-init: array<string, array{url: string}>} $configuredData */
        $configuredData = $configured->getData('data_attribute');
        /** @var array{mage-init: array<string, array{url: string}>} $defaultData */
        $defaultData = $default->getData('data_attribute');
        self::assertSame(
            'configured-url',
            $configuredData['mage-init']['DavidBel_AiSearch/js/test-embedder-connection']['url']
        );
        self::assertSame(
            'default-url',
            $defaultData['mage-init']['DavidBel_AiSearch/js/test-embedder-connection']['url']
        );
    }

    public function testSemanticSearchFieldCreatesButtonBlock(): void
    {
        $button = self::createStub(SemanticSearchButton::class);
        $button->method('toHtml')->willReturn('<button>Semantic</button>');
        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects(self::once())
            ->method('createBlock')
            ->with(
                SemanticSearchButton::class,
                '',
                ['data' => ['id' => 'semantic-button']]
            )
            ->willReturn($button);
        $field = $this->getMockBuilder(TestableSemanticSearchField::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getLayout'])
            ->getMock();
        $field->method('getLayout')->willReturn($layout);
        $element = self::createStub(AbstractElement::class);
        $element->method('getHtmlId')->willReturn('semantic-button');

        self::assertSame('<button>Semantic</button>', $field->elementHtml($element));
    }

    public function testSemanticSearchFieldRemovesScopeControlsBeforeRendering(): void
    {
        $element = new Text(
            self::createStub(Factory::class),
            self::createStub(CollectionFactory::class),
            new Escaper(),
            []
        );
        $element->setForm(self::createStub(Form::class));
        $field = $this->getMockBuilder(TestableSemanticSearchField::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                '_isInheritCheckboxRequired',
                '_renderScopeLabel',
                '_renderValue',
                '_renderHint',
                '_decorateRowHtml',
            ])
            ->getMock();
        $field->method('_isInheritCheckboxRequired')->willReturn(false);
        $field->method('_renderScopeLabel')->willReturn('');
        $field->method('_renderValue')->willReturn('<td>value</td>');
        $field->method('_renderHint')->willReturn('<td>hint</td>');
        $field->method('_decorateRowHtml')->willReturnArgument(1);

        self::assertStringContainsString('<td>value</td>', $field->render($element));
    }

    public function testEmbedderConnectionFieldRemovesScopeControlsBeforeRendering(): void
    {
        $element = new Text(
            self::createStub(Factory::class),
            self::createStub(CollectionFactory::class),
            new Escaper(),
            []
        );
        $element->setForm(self::createStub(Form::class));
        $field = $this->getMockBuilder(TestableConnectionField::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                '_isInheritCheckboxRequired',
                '_renderScopeLabel',
                '_renderValue',
                '_renderHint',
                '_decorateRowHtml',
            ])
            ->getMock();
        $field->method('_isInheritCheckboxRequired')->willReturn(false);
        $field->method('_renderScopeLabel')->willReturn('');
        $field->method('_renderValue')->willReturn('<td>value</td>');
        $field->method('_renderHint')->willReturn('<td>hint</td>');
        $field->method('_decorateRowHtml')->willReturnArgument(1);

        self::assertStringContainsString('<td>value</td>', $field->render($element));
    }

    public function testDashboardNavigationAddsAndPrependsNavigation(): void
    {
        $navigation = $this->createMock(Template::class);
        $navigation->expects(self::exactly(2))->method('addChild');
        $layout = self::createStub(LayoutInterface::class);
        $layout->method('createBlock')->willReturnOnConsecutiveCalls(
            self::createStub(ElementRenderer::class),
            self::createStub(FieldsetRenderer::class),
            self::createStub(FieldsetElementRenderer::class)
        );
        $block = $this->getMockBuilder(TestableDashboardNavigation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addChild', 'getChildHtml', 'getLayout', '_getDependence'])
            ->getMock();
        $block->method('getLayout')->willReturn($layout);
        $block->method('_getDependence')->willReturn(false);
        $block->expects(self::once())
            ->method('addChild')
            ->with(
                'dashboard_navigation',
                Template::class,
                ['template' => 'DavidBel_AiSearch::system/config/dashboard-navigation.phtml']
            )
            ->willReturn($navigation);
        $block->expects(self::once())
            ->method('getChildHtml')
            ->with('dashboard_navigation')
            ->willReturn('<nav>Dashboard</nav>');

        self::assertSame($block, $block->prepareLayout());
        self::assertSame('<nav>Dashboard</nav><form>Config</form>', $block->appendNavigation('<form>Config</form>'));
    }

    private function context(string $url = ''): Context
    {
        $urlBuilder = self::createStub(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturn($url);
        $context = self::createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($urlBuilder);

        return $context;
    }

    private function store(int $id, string $code, string $name, bool $active): StoreInterface
    {
        $store = self::createStub(StoreInterface::class);
        $store->method('getId')->willReturn($id);
        $store->method('getCode')->willReturn($code);
        $store->method('getName')->willReturn($name);
        $store->method('getIsActive')->willReturn($active);

        return $store;
    }
}
