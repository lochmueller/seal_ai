<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Document\Transformer;

use Lochmueller\SealAi\Document\Transformer\TextSplitTransformer;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Symfony\AI\Store\Document\EmbeddableDocumentInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;

class TextSplitTransformerTest extends AbstractTest
{
    public function testShortDocumentIsNotSplit(): void
    {
        $transformer = new TextSplitTransformer(chunkSize: 100, overlap: 10);

        $documents = [...$transformer->transform([new TextDocument('doc', 'Short text')])];

        self::assertCount(1, $documents);
        self::assertSame('doc', $documents[0]->getId());
        self::assertSame('Short text', $documents[0]->getMetadata()->getText());
    }

    public function testLongDocumentIsSplitIntoChunks(): void
    {
        $transformer = new TextSplitTransformer(chunkSize: 10, overlap: 2);

        $documents = [...$transformer->transform([new TextDocument('doc', str_repeat('a', 25))])];

        self::assertSame(['doc-0', 'doc-1', 'doc-2', 'doc-3'], array_map(static fn(EmbeddableDocumentInterface $document): int|string => $document->getId(), $documents));
        self::assertSame('doc', $documents[0]->getMetadata()->getParentId());
    }

    public function testChunkSizeGrowsToRespectMaxItems(): void
    {
        $transformer = new TextSplitTransformer(maxItems: 2, chunkSize: 10, overlap: 0);

        $documents = [...$transformer->transform([new TextDocument('doc', str_repeat('a', 41))])];

        self::assertCount(2, $documents);
        self::assertSame(21, mb_strlen((string) $documents[0]->getContent()));
        self::assertSame(20, mb_strlen((string) $documents[1]->getContent()));
    }

    public function testNonTextDocumentIsPassedThrough(): void
    {
        $document = new class implements EmbeddableDocumentInterface {
            public function getId(): int|string
            {
                return 'image';
            }

            public function getContent(): string|object
            {
                return new \stdClass();
            }

            public function getMetadata(): Metadata
            {
                return new Metadata();
            }
        };

        $documents = [...(new TextSplitTransformer())->transform([$document])];

        self::assertSame([$document], $documents);
    }
}
