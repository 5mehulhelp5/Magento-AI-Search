<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Controller;

use DavidBel\AiSearch\Controller\Adminhtml\AiServer\TestEmbedderConnection;

class TestableEmbedderConnectionAction extends TestEmbedderConnection
{
    public function isAllowed(): bool
    {
        return $this->_isAllowed();
    }
}
