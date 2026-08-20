<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class EmbeddingApiProtocol implements OptionSourceInterface
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'openai_compatible',
                'label' => (string) __('OpenAI-Compatible'),
            ],
            [
                'value' => 'google_gemini_native',
                'label' => (string) __('Google Gemini Native'),
            ],
        ];
    }
}
