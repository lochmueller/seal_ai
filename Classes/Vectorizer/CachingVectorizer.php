<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Vectorizer;

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Document\EmbeddableDocumentInterface;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\Document\VectorizerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Content-hash embedding cache: unchanged texts are answered from the TYPO3 caching framework,
 * only cache misses are passed to the wrapped vectorizer (platform DSN or aim://).
 */
readonly class CachingVectorizer implements VectorizerInterface
{
    public const CACHE_IDENTIFIER = 'seal_ai_embedding';

    /**
     * Increase, if the normalization changes, so all cache keys change.
     */
    private const NORMALIZER_VERSION = 1;

    public function __construct(
        private VectorizerInterface $vectorizer,
        private FrontendInterface $cache,
        private string $fingerprint,
    ) {}

    /**
     * Configuration fingerprint. API keys, hosts and failover order are not part of it, they do not change the vectors.
     */
    public static function createFingerprint(string $provider, string $model, int $dimensions): string
    {
        return hash('sha256', (string) json_encode([
            'provider' => $provider,
            'model' => $model,
            'dimensions' => $dimensions,
            'normalizerVersion' => self::NORMALIZER_VERSION,
        ]));
    }

    public function vectorize(string|\Stringable|EmbeddableDocumentInterface|array $values, array $options = []): Vector|VectorDocumentInterface|array
    {
        $single = !\is_array($values);
        $items = $single ? [$values] : array_values($values);
        if ($items === []) {
            return [];
        }

        $result = [];
        $misses = [];
        $missKeys = [];
        foreach ($items as $index => $item) {
            $cacheKey = $this->getCacheKey($item);
            $vector = $cacheKey !== null ? $this->getCachedVector($cacheKey) : null;
            if ($vector !== null) {
                $result[$index] = $item instanceof EmbeddableDocumentInterface
                    ? new VectorDocument($item->getId(), $vector, $item->getMetadata())
                    : $vector;
                continue;
            }
            $misses[$index] = $item;
            $missKeys[$index] = $cacheKey;
        }

        if ($misses !== []) {
            // Misses are passed through in the same shape (single value stays single value)
            $vectorized = $single
                ? [$this->vectorizer->vectorize($items[0], $options)]
                : array_values($this->vectorizer->vectorize(array_values($misses), $options));

            foreach (array_keys($misses) as $position => $index) {
                $vectorResult = $vectorized[$position] ?? null;
                if (!$vectorResult instanceof VectorInterface && !$vectorResult instanceof VectorDocumentInterface) {
                    throw new \RuntimeException('The vectorizer did not return a vector for every value', 1760000001);
                }
                $result[$index] = $vectorResult;
                if ($missKeys[$index] !== null) {
                    $this->setCachedVector(
                        $missKeys[$index],
                        $vectorResult instanceof VectorDocumentInterface ? $vectorResult->getVector() : $vectorResult,
                    );
                }
            }
        }

        ksort($result);

        return $single ? $result[0] : array_values($result);
    }

    private function getCacheKey(mixed $item): ?string
    {
        if ($item instanceof EmbeddableDocumentInterface) {
            $item = $item->getContent();
        }

        if (!\is_string($item) && !$item instanceof \Stringable) {
            return null;
        }

        return hash('sha256', $this->fingerprint . "\x1F" . $this->normalize((string) $item));
    }

    private function normalize(string $text): string
    {
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }

        $normalized = preg_replace(['/\s+/u', '/[\p{Cc}\p{Cf}]/u'], [' ', ''], $text);

        return trim($normalized ?? $text);
    }

    private function getCachedVector(string $cacheKey): ?Vector
    {
        $entry = $this->cache->get($cacheKey);
        if (!\is_array($entry) || !\is_string($entry['vector'] ?? null)) {
            return null;
        }

        $data = unpack('g*', $entry['vector']);
        if (!\is_array($data) || $data === []) {
            return null;
        }

        return new Vector(array_map(floatval(...), array_values($data)));
    }

    private function setCachedVector(string $cacheKey, VectorInterface $vector): void
    {
        // NullVector: the platform returned no embedding, which must not be cached
        if (!$vector instanceof Vector) {
            return;
        }

        $this->cache->set($cacheKey, [
            'dimensions' => $vector->getDimensions(),
            'vector' => pack('g*', ...$vector->getData()),
        ], ['seal_ai_config_' . $this->fingerprint]);
    }
}
