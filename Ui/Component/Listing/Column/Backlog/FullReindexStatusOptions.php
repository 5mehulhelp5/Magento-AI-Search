<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ui\Component\Listing\Column\Backlog;

use DavidBel\AiSearch\Model\EmbeddingBacklog\FullReindexStatus;
use Magento\Framework\Data\OptionSourceInterface;

class FullReindexStatusOptions implements OptionSourceInterface
{
    /**
     * @return list<array{value: int, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];

        foreach (FullReindexStatus::cases() as $status) {
            $options[] = [
                'value' => $status->value,
                'label' => __($status->name),
            ];
        }

        return $options;
    }
}
