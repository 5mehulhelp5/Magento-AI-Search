<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model\EmbeddingBacklog;

enum Status: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Failed = 'failed';
}
