<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;

class ChunkProcessingCleanup
{
    private const int ATTEMPT_THRESHOLD = 3;
    private const string DONE_RETENTION = '-24 hours';

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly DateTime $dateTime
    ) {
    }

    public function execute(): int
    {
        $doneBefore = $this->dateTime->gmtDate(null, self::DONE_RETENTION);

        return $this->collectionFactory
            ->create()
            ->getResourceModel()
            ->deleteExhaustedUpsertsOrDoneBefore(
                self::ATTEMPT_THRESHOLD,
                $doneBefore
            );
    }
}
