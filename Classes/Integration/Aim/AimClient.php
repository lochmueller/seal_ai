<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Integration\Aim;

use B13\Aim\Ai;
use B13\Aim\Request\Message\UserMessage;
use B13\Aim\Response\EmbeddingResponse;

/**
 * Thin wrapper around the (final) AiM proxy, so the integration stays mockable.
 */
class AimClient
{
    public const EXTENSION_KEY = 'seal_ai';

    public function __construct(
        private readonly Ai $ai,
    ) {}

    /**
     * @param list<string> $texts
     *
     * @return list<list<float>>
     */
    public function embed(array $texts, string $provider = '', int $dimensions = 0): array
    {
        $response = $this->ai->embed($texts, $dimensions, extensionKey: self::EXTENSION_KEY, provider: $provider);

        if (!$response instanceof EmbeddingResponse || !$response->isSuccessful()) {
            throw new \RuntimeException('AiM embedding request failed: ' . implode(', ', $response->errors), 1759312001);
        }

        if (\count($response->embeddings) !== \count($texts)) {
            throw new \RuntimeException(
                \sprintf('AiM returned %d embeddings for %d inputs', \count($response->embeddings), \count($texts)),
                1759312003
            );
        }

        return $response->embeddings;
    }

    public function complete(string $systemPrompt, string $userPrompt, string $provider = ''): string
    {
        $response = $this->ai->conversation(
            [new UserMessage($userPrompt)],
            systemPrompt: $systemPrompt,
            extensionKey: self::EXTENSION_KEY,
            provider: $provider,
        );

        if ($response->errors !== []) {
            throw new \RuntimeException('AiM conversation request failed: ' . implode(', ', $response->errors), 1759312004);
        }

        return $response->content;
    }
}
