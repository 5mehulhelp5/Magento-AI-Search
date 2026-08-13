<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Chunk extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('davidbel_ai_search_chunk', 'chunk_id');
    }
}
