<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ui\Component\Listing\Column\Backlog;

use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use Magento\Framework\Data\OptionSourceInterface;

class StatusOptions implements OptionSourceInterface
{
    /**
     * @return list<array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];

        foreach (Status::cases() as $status) {
            $options[] = [
                'value' => $status->value,
                'label' => __($status->name),
            ];
        }

        return $options;
    }
}
