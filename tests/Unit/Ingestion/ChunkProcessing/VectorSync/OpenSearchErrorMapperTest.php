<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\ChunkProcessing\VectorSync;

use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\OpenSearchErrorMapper;
use DavidBel\AiSearch\Model\EmbeddingBacklog\ErrorDetails;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OpenSearchErrorMapperTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $operation
     */
    #[DataProvider('operations')]
    public function testMapsOpenSearchErrors(array $operation, ErrorDetails $expected): void
    {
        self::assertEquals($expected, (new OpenSearchErrorMapper())->map($operation));
    }

    /**
     * @return array<string, array{array<array-key, mixed>, ErrorDetails}>
     */
    public static function operations(): array
    {
        return [
            'plain message' => [
                ['status' => 400, 'error' => 'invalid document'],
                new ErrorDetails('400', 'invalid document'),
            ],
            'type and reason' => [
                ['status' => 409, 'error' => ['type' => 'conflict', 'reason' => 'outdated']],
                new ErrorDetails('409', 'conflict: outdated'),
            ],
            'reason only' => [
                ['error' => ['reason' => 'rejected']],
                new ErrorDetails(null, 'rejected'),
            ],
            'type only' => [
                ['error' => ['type' => 'parse_error']],
                new ErrorDetails(null, 'parse_error'),
            ],
            'status fallback' => [
                ['status' => 500, 'error' => '  '],
                new ErrorDetails('500', 'OpenSearch bulk item failed with HTTP status 500.'),
            ],
            'generic fallback' => [
                ['error' => []],
                new ErrorDetails(null, 'OpenSearch bulk item failed.'),
            ],
        ];
    }
}
