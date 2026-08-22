<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Block;

use DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field\Documents;
use Magento\Framework\DataObject;

class TestableDocuments extends Documents
{
    public function prepareToRender(): void
    {
        $this->_prepareToRender();
    }

    public function prepareArrayRow(DataObject $row): void
    {
        $this->_prepareArrayRow($row);
    }

    public function addsAfter(): bool
    {
        return $this->_addAfter;
    }

    public function buttonLabel(): string
    {
        return $this->_addButtonLabel;
    }
}
