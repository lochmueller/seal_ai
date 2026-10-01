<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Integration\Aim;

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\EmbeddableDocumentInterface;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\Document\VectorizerInterface;

readonly class AimVectorizer implements VectorizerInterface
{
    public function __construct(
        private AimClient $client,
        private string $provider = '',
        private int $dimensions = 0,
    ) {}

    public function vectorize(string|\Stringable|EmbeddableDocumentInterface|array $values, array $options = []): Vector|VectorDocumentInterface|array
    {
        $single = !\is_array($values);
        $items = $single ? [$values] : array_values($values);
        if ($items === []) {
            return [];
        }

        $texts = array_map(static fn(mixed $item): string => self::toText($item), $items);
        $embeddings = $this->client->embed($texts, $this->provider, $this->dimensions);

        $result = [];
        foreach ($items as $index => $item) {
            $vector = new Vector($embeddings[$index]);
            $result[] = $item instanceof EmbeddableDocumentInterface
                ? new VectorDocument($item->getId(), $vector, $item->getMetadata())
                : $vector;
        }

        return $single ? $result[0] : $result;
    }

    private static function toText(mixed $item): string
    {
        if ($item instanceof EmbeddableDocumentInterface) {
            $item = $item->getContent();
        }

        if (\is_string($item) || $item instanceof \Stringable) {
            return (string) $item;
        }

        throw new \InvalidArgumentException(
            \sprintf('AiM vectorizer only supports text content, "%s" given', get_debug_type($item)),
            1759312005
        );
    }
}
