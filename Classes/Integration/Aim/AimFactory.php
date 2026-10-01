<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Integration\Aim;

use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\SealAi\Chat\ChatInterface;
use Symfony\AI\Store\Document\VectorizerInterface;

/**
 * Only registered in the DI container, if EXT:aim is installed (see Configuration/Services.php).
 */
readonly class AimFactory
{
    /**
     * Value for "sealAiChatModel" to use the default AiM provider for conversations.
     */
    public const DEFAULT_PROVIDER = 'default';

    public function __construct(
        private AimClient $client,
    ) {}

    /**
     * DSN Examples:
     * aim://default
     * aim://default?model=openai:text-embedding-3-small&dimensions=768
     */
    public function createVectorizer(DsnDto $dsn): VectorizerInterface
    {
        $model = $dsn->query['model'] ?? '';
        $dimensions = $dsn->query['dimensions'] ?? 0;

        return new AimVectorizer(
            $this->client,
            \is_string($model) ? $model : '',
            \is_numeric($dimensions) ? (int) $dimensions : 0,
        );
    }

    public function createChat(string $provider): ChatInterface
    {
        return new AimChat($this->client, $provider === self::DEFAULT_PROVIDER ? '' : $provider);
    }
}
