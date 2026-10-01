<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Chat;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

readonly class PlatformChat implements ChatInterface
{
    /**
     * @param non-empty-string $model
     */
    public function __construct(
        private PlatformInterface $platform,
        private string $model,
    ) {}

    public function complete(string $systemPrompt, string $userPrompt): string
    {
        $messages = new MessageBag(
            Message::forSystem($systemPrompt),
            Message::ofUser($userPrompt),
        );

        return $this->platform->invoke($this->model, $messages)->asText();
    }
}
