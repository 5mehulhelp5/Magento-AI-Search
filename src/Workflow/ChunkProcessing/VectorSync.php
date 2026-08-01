<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\ChunkProcessing;

use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Delete;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Delete\Batch as DeleteBatch;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Result;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Upsert;

class VectorSync
{
    public function __construct(
        private readonly Upsert $upsert,
        private readonly Delete $delete
    ) {
    }

    /**
     * @param list<list<float>> $vectors
     */
    public function upsert(ProcessingBatch $batch, array $vectors): Result
    {
        return $this->upsert->execute($batch, $vectors);
    }

    public function delete(DeleteBatch $batch): Result
    {
        return $this->delete->execute($batch);
    }
}
