<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Chat;

interface ChatInterface
{
    public function complete(string $systemPrompt, string $userPrompt): string;
}
