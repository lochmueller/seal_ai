<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Vectorizer;

use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Lochmueller\SealAi\Vectorizer\CachingVectorizer;
use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\EmbeddableDocumentInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\Document\VectorizerInterface;
use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Information\Typo3Version;

class CachingVectorizerTest extends AbstractTest
{
    protected bool $resetSingletonInstances = true;

    public function testSecondCallWithSameTextDoesNotCallInnerVectorizer(): void
    {
        $inner = $this->createCountingVectorizer();
        $vectorizer = $this->createCachingVectorizer($inner);

        $first = $vectorizer->vectorize('Hello world');
        $second = $vectorizer->vectorize('Hello world');

        self::assertSame(1, $inner->calls);
        self::assertInstanceOf(Vector::class, $first);
        self::assertInstanceOf(Vector::class, $second);
        self::assertEqualsWithDelta($first->getData(), $second->getData(), 0.00001);
    }

    public function testNormalizedTextSharesCacheEntry(): void
    {
        $inner = $this->createCountingVectorizer();
        $vectorizer = $this->createCachingVectorizer($inner);

        $vectorizer->vectorize('Hello world');
        $vectorizer->vectorize("  Hello \n\t world\u{200B} ");

        self::assertSame(1, $inner->calls);
    }

    public function testMixedDocumentsPassOnlyMissesAndKeepOrder(): void
    {
        $inner = $this->createCountingVectorizer();
        $vectorizer = $this->createCachingVectorizer($inner);

        $vectorizer->vectorize([new TextDocument('a', 'Alpha')]);

        $result = $vectorizer->vectorize([
            new TextDocument('b', 'Beta', new Metadata(['title' => 'B'])),
            new TextDocument('a', 'Alpha', new Metadata(['title' => 'A'])),
            new TextDocument('c', 'Gamma'),
        ]);

        self::assertSame([['Alpha'], ['Beta', 'Gamma']], $inner->texts);
        self::assertIsArray($result);
        self::assertSame(['b', 'a', 'c'], array_map(static fn(VectorDocumentInterface $document): int|string => $document->getId(), $result));
        self::assertSame('A', $result[1]->getMetadata()['title']);
        self::assertEqualsWithDelta([5.0, 1.0], $result[1]->getVector()->getData(), 0.00001);
    }

    public function testDifferentFingerprintDoesNotShareCacheEntry(): void
    {
        $cache = new VariableFrontend(CachingVectorizer::CACHE_IDENTIFIER, $this->createMemoryBackend());
        $inner = $this->createCountingVectorizer();

        (new CachingVectorizer($inner, $cache, CachingVectorizer::createFingerprint('openai', 'model-a', 0)))->vectorize('Text');
        (new CachingVectorizer($inner, $cache, CachingVectorizer::createFingerprint('openai', 'model-b', 0)))->vectorize('Text');

        self::assertSame(2, $inner->calls);
    }

    public function testFingerprintChangesWithEveryField(): void
    {
        $base = CachingVectorizer::createFingerprint('openai', 'text-embedding-3-small', 768);

        self::assertSame($base, CachingVectorizer::createFingerprint('openai', 'text-embedding-3-small', 768));
        self::assertNotSame($base, CachingVectorizer::createFingerprint('mistral', 'text-embedding-3-small', 768));
        self::assertNotSame($base, CachingVectorizer::createFingerprint('openai', 'text-embedding-3-large', 768));
        self::assertNotSame($base, CachingVectorizer::createFingerprint('openai', 'text-embedding-3-small', 512));
    }

    public function testNullVectorIsNotCached(): void
    {
        $inner = $this->createMock(VectorizerInterface::class);
        $inner->expects(self::exactly(2))->method('vectorize')->willReturn([new VectorDocument('a', new NullVector())]);

        $vectorizer = $this->createCachingVectorizer($inner);
        $vectorizer->vectorize([new TextDocument('a', 'Text')]);
        $vectorizer->vectorize([new TextDocument('a', 'Text')]);
    }

    public function testEntriesAreTaggedWithFingerprint(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('get')->willReturn(false);
        $cache->expects(self::once())->method('set')->with(
            self::isString(),
            self::isArray(),
            ['seal_ai_config_' . CachingVectorizer::createFingerprint('openai', 'model', 0)],
        );

        (new CachingVectorizer($this->createCountingVectorizer(), $cache, CachingVectorizer::createFingerprint('openai', 'model', 0)))
            ->vectorize('Text');
    }

    public function testEmptyArrayReturnsEmptyArray(): void
    {
        $inner = $this->createCountingVectorizer();

        self::assertSame([], $this->createCachingVectorizer($inner)->vectorize([]));
        self::assertSame(0, $inner->calls);
    }

    private function createCachingVectorizer(VectorizerInterface $inner): CachingVectorizer
    {
        return new CachingVectorizer(
            $inner,
            new VariableFrontend(CachingVectorizer::CACHE_IDENTIFIER, $this->createMemoryBackend()),
            CachingVectorizer::createFingerprint('openai', 'text-embedding-3-small', 0),
        );
    }

    private function createMemoryBackend(): TransientMemoryBackend
    {
        // TYPO3 v13 still requires the application context as first constructor argument
        if ((new Typo3Version())->getMajorVersion() < 14) {
            return new TransientMemoryBackend('Testing');
        }

        return new TransientMemoryBackend();
    }

    /**
     * Returns the vector [length of text, 1.0] and records every call.
     */
    private function createCountingVectorizer(): VectorizerInterface
    {
        return new class implements VectorizerInterface {
            public int $calls = 0;

            /**
             * @var list<list<string>>
             */
            public array $texts = [];

            public function vectorize(string|\Stringable|EmbeddableDocumentInterface|array $values, array $options = []): Vector|VectorDocumentInterface|array
            {
                $this->calls++;
                $single = !\is_array($values);
                $items = $single ? [$values] : array_values($values);

                $texts = [];
                $result = [];
                foreach ($items as $item) {
                    $text = $item instanceof EmbeddableDocumentInterface ? (string) $item->getContent() : (string) $item;
                    $texts[] = $text;
                    $vector = new Vector([(float) mb_strlen($text), 1.0]);
                    $result[] = $item instanceof EmbeddableDocumentInterface
                        ? new VectorDocument($item->getId(), $vector, $item->getMetadata())
                        : $vector;
                }
                $this->texts[] = $texts;

                return $single ? $result[0] : $result;
            }
        };
    }
}
