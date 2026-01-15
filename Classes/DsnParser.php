<?php

declare(strict_types=1);

namespace Lochmueller\SealAi;

use Lochmueller\SealAi\Dto\DsnDto;

class DsnParser
{
    public function parse(string $dsn): DsnDto
    {
        $parts = parse_url($dsn);

        if ($parts === false || !isset($parts['scheme'])) {
            throw new \InvalidArgumentException('Invalid DSN format');
        }

        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        return new DsnDto(
            scheme: $parts['scheme'],
            user: $parts['user'] ?? null,
            password: $parts['pass'] ?? null,
            host: $parts['host'] ?? null,
            port: $parts['port'] ?? null,
            path: isset($parts['path']) ? ltrim($parts['path'], '/') : null,
            query: $query,
        );
    }
}
