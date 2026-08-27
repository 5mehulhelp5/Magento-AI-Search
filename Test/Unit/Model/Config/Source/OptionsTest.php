<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Model\Config\Source;

use DavidBel\AiSearch\Model\Config\Source\EmbeddingApiProtocol;
use DavidBel\AiSearch\Model\Config\Source\VectorEngine;
use DavidBel\AiSearch\Model\Config\Source\VectorSpace;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{
    public function testProvidesEmbeddingProtocols(): void
    {
        self::assertSame(
            [
                ['value' => 'openai_compatible', 'label' => 'OpenAI-Compatible'],
                ['value' => 'google_gemini_native', 'label' => 'Google Gemini Native'],
            ],
            (new EmbeddingApiProtocol())->toOptionArray()
        );
    }

    public function testProvidesVectorEngines(): void
    {
        self::assertSame(
            [
                ['value' => 'faiss', 'label' => 'Faiss'],
                ['value' => 'lucene', 'label' => 'Lucene'],
            ],
            (new VectorEngine())->toOptionArray()
        );
    }

    public function testProvidesVectorSpaces(): void
    {
        self::assertSame(
            [
                ['value' => 'l2', 'label' => 'L2'],
                ['value' => 'cosinesimil', 'label' => 'Cosine Similarity'],
                ['value' => 'innerproduct', 'label' => 'Inner Product'],
            ],
            (new VectorSpace())->toOptionArray()
        );
    }
}
