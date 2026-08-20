<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Client\Embedding\GoogleGemini;

use DavidBel\AiSearch\Client\Embedding\Base\EmbeddingInput;

class RequestBuilder
{
    private const string DOCUMENT_TASK_TYPE = 'RETRIEVAL_DOCUMENT';
    private const string QUERY_TASK_TYPE = 'RETRIEVAL_QUERY';

    /**
     * @param list<EmbeddingInput> $inputs
     * @return array{requests: list<array<string, mixed>>}
     */
    public function buildDocumentRequestBody(
        array $inputs,
        string $embeddingModel,
        int $vectorDimensions,
        string $documentTemplate
    ): array {
        $requests = [];
        $model = $this->getModelResourceName($embeddingModel);

        foreach ($inputs as $input) {
            $requests[] = $this->createRequest(
                $input,
                $model,
                $vectorDimensions,
                $documentTemplate
            );
        }

        return ['requests' => $requests];
    }

    /**
     * @return array{requests: list<array<string, mixed>>}
     */
    public function buildQueryRequestBody(
        string $queryText,
        string $embeddingModel,
        int $vectorDimensions,
        string $queryTemplate
    ): array {
        return [
            'requests' => [
                [
                    'model' => $this->getModelResourceName($embeddingModel),
                    'content' => [
                        'parts' => [
                            [
                                'text' => strtr(
                                    $queryTemplate,
                                    ['{text}' => $queryText]
                                ),
                            ],
                        ],
                    ],
                    'taskType' => self::QUERY_TASK_TYPE,
                    'outputDimensionality' => $vectorDimensions,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createRequest(
        EmbeddingInput $input,
        string $model,
        int $vectorDimensions,
        string $documentTemplate
    ): array {
        $request = [
            'model' => $model,
            'content' => [
                'parts' => [
                    [
                        'text' => strtr(
                            $documentTemplate,
                            [
                                '{title}' => $input->title ?? 'none',
                                '{text}' => $input->text,
                            ]
                        ),
                    ],
                ],
            ],
            'taskType' => self::DOCUMENT_TASK_TYPE,
            'outputDimensionality' => $vectorDimensions,
        ];

        if ($input->title !== null && $input->title !== '') {
            $request['title'] = $input->title;
        }

        return $request;
    }

    private function getModelResourceName(string $embeddingModel): string
    {
        if (str_starts_with($embeddingModel, 'models/')) {
            return $embeddingModel;
        }

        return 'models/' . $embeddingModel;
    }
}
