<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Dto;

class DsnDto
{
    public function __construct(
        public readonly string $scheme,
        #[\SensitiveParameter]
        public readonly ?string $user = null,
        #[\SensitiveParameter]
        public readonly ?string $password = null,
        public readonly ?string $host = null,
        public readonly ?int $port = null,
        public readonly ?string $path = null,
        public readonly array $query = [],
    ) {}
}
