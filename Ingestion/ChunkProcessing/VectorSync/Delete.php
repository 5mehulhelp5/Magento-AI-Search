<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync;

use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete\Batch;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete\RequestBuilder;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete\ResponseMapper;

class Delete
{
    public function __construct(
        private readonly OpenSearch $openSearch,
        private readonly RequestBuilder $requestBuilder,
        private readonly ResponseMapper $responseMapper
    ) {
    }

    public function execute(Batch $batch): Result
    {
        $items = $batch->getItems();
        $response = $this->openSearch->bulkQuery(
            $batch->getIndexVersion(),
            $this->requestBuilder->build($batch)
        );

        return $this->responseMapper->map($response, $items);
    }
}
