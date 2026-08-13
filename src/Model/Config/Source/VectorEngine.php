<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class VectorEngine implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'faiss',
                'label' => (string) __('Faiss'),
            ],
            [
                'value' => 'lucene',
                'label' => (string) __('Lucene'),
            ],
        ];
    }
}
