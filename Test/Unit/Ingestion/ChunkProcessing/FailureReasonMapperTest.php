<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Ingestion\ChunkProcessing;

use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingResultHandler\FailureReasonMapper;
use DavidBel\AiSearch\Model\EmbeddingBacklog\ErrorDetails;
use DavidBel\AiSearch\Test\Unit\Ingestion\ChunkProcessing\TestDouble\TimeoutFailure;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use OpenSearch\Exception\HttpException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FailureReasonMapperTest extends TestCase
{
    #[DataProvider('failureReasons')]
    public function testMapsFailureReason(mixed $reason, ErrorDetails $expected): void
    {
        self::assertEquals($expected, (new FailureReasonMapper())->map($reason));
    }

    /**
     * @return array<string, array{mixed, ErrorDetails}>
     */
    public static function failureReasons(): array
    {
        $request = new Request('GET', 'https://example.test');

        return [
            'not throwable' => [
                'failure',
                new ErrorDetails(null, 'Processing failed without an exception.'),
            ],
            'curl timeout' => [
                new ConnectException('timeout', $request, null, ['errno' => 28]),
                new ErrorDetails('timeout', 'The remote request timed out.'),
            ],
            'named timeout' => [
                new TimeoutFailure('slow'),
                new ErrorDetails('timeout', 'The remote request timed out.'),
            ],
            'connection' => [
                new ConnectException('connection', $request),
                new ErrorDetails('connection_error', 'The remote service could not be reached.'),
            ],
            'guzzle response' => [
                new RequestException('failed', $request, new Response(503)),
                new ErrorDetails('503', 'The remote request failed with HTTP status 503.'),
            ],
            'opensearch response' => [
                new HttpException(429),
                new ErrorDetails('429', 'OpenSearch request failed with HTTP status 429.'),
            ],
            'generic with code' => [
                new RuntimeException('generic', 12),
                new ErrorDetails('12', 'generic'),
            ],
            'generic without code' => [
                new RuntimeException('generic'),
                new ErrorDetails(null, 'generic'),
            ],
        ];
    }
}
