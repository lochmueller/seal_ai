<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Integration\Aim;

use Lochmueller\SealAi\Chat\ChatInterface;

readonly class AimChat implements ChatInterface
{
    public function __construct(
        private AimClient $client,
        private string $provider = '',
    ) {}

    public function complete(string $systemPrompt, string $userPrompt): string
    {
        return $this->client->complete($systemPrompt, $userPrompt, $this->provider);
    }
}
