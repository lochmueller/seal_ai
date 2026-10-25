<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Document\Transformer;

use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\TransformerInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;

class TextSplitTransformer implements TransformerInterface
{
    public const OPTION_CHUNK_SIZE = 'chunk_size';
    public const OPTION_OVERLAP = 'overlap';

    public function __construct(
        private readonly int $maxItems = 10,
        private readonly int $chunkSize = 1000,
        private readonly int $overlap = 200,
    ) {
        if ($this->overlap < 0 || $this->overlap >= $this->chunkSize) {
            throw new InvalidArgumentException(\sprintf('Overlap must be non-negative and less than chunk size. Got chunk size: %d, overlap: %d.', $this->chunkSize, $this->overlap));
        }
    }

    /**
     * @param array{chunk_size?: int, overlap?: int} $options
     */
    public function transform(iterable $documents, array $options = []): iterable
    {
        $chunkSize = $options[self::OPTION_CHUNK_SIZE] ?? $this->chunkSize;
        $overlap = $options[self::OPTION_OVERLAP] ?? $this->overlap;

        if ($overlap < 0 || $overlap >= $chunkSize) {
            throw new InvalidArgumentException('Overlap must be non-negative and less than chunk size.');
        }

        foreach ($documents as $document) {
            $text = $document->getContent();
            if (!\is_string($text)) {
                // Non-text content (e.g. images) cannot be split and is passed through unchanged.
                yield $document;

                continue;
            }

            if (mb_strlen($text) <= $chunkSize) {
                $metadata = new Metadata([
                    ...$document->getMetadata(),
                    Metadata::KEY_TEXT => $text,
                ]);

                yield new TextDocument($document->getId(), $text, $metadata);

                continue;
            }

            $currentChuckSize = $chunkSize;
            $length = mb_strlen($text);
            if ($length / $chunkSize > $this->maxItems) {
                // Integer ceil($length / $maxItems)
                $currentChuckSize = intdiv($length + $this->maxItems - 1, $this->maxItems);
            }

            $start = 0;
            $count = 0;

            while ($start < $length) {
                $end = min($start + $currentChuckSize, $length);
                $chunkText = mb_substr($text, $start, $end - $start);

                $meta = [
                    ...$document->getMetadata(),
                    Metadata::KEY_PARENT_ID => $document->getId(),
                    Metadata::KEY_TEXT => $chunkText,
                ];
                $meta['content'] = $chunkText;
                yield new TextDocument($document->getId() . '-' . $count, $chunkText, new Metadata($meta));

                $count++;
                $start += ($currentChuckSize - $overlap);
            }
        }
    }
}
