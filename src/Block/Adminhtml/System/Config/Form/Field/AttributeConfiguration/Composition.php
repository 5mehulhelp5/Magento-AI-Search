<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field\AttributeConfiguration;

use Magento\Framework\View\Element\Html\Select;

class Composition extends Select
{
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

        if ($this->getOptions() === []) {
            $this->addOption('0', (string) __('No — Current Product Only'));
            $this->addOption('1', (string) __('Yes — Include Child Products'));
        }

        return parent::_toHtml();
    }
}
