<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class VectorSpace implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'l2',
                'label' => (string) __('L2'),
            ],
            [
                'value' => 'cosinesimil',
                'label' => (string) __('Cosine Similarity'),
            ],
            [
                'value' => 'innerproduct',
                'label' => (string) __('Inner Product'),
            ],
        ];
    }
}
