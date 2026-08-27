<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model\EmbeddingBacklog;

enum FullReindexStatus: int
{
    case Delta = 0;
    case Pending = 1;
    case Indexed = 2;
}
