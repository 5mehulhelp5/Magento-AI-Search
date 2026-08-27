<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field\AttributeConfiguration;

use DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class ParsingStrategy extends Select
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Parsing $parsing,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function setInputName(string $inputName): self
    {
        $this->setData('name', $inputName);

        return $this;
    }

    public function setInputId(string $inputId): self
    {
        $this->setId($inputId);

        return $this;
    }

    protected function _toHtml(): string
    {
        $this->setClass('required-entry admin__control-select');

        if ($this->getOptions() !== []) {
            return parent::_toHtml();
        }

        $this->addOption('', (string) __('-- Select Parsing Strategy --'));

        foreach ($this->parsing->getAvailableStrategies() as $parsingStrategy) {
            $this->addOption(
                $parsingStrategy->getCode(),
                $this->getLabel($parsingStrategy->getCode())
            );
        }

        return parent::_toHtml();
    }

    private function getLabel(string $code): string
    {
        return ucwords(str_replace('_', ' ', $code));
    }
}
