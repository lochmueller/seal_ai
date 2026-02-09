<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Event;

use Lochmueller\Seal\Dto\DsnDto;
use Symfony\AI\Platform\PlatformInterface;

final class PlatformFactoryEvent
{
    private ?PlatformInterface $platform = null;

    public function __construct(
        private readonly DsnDto $dsn,
    ) {}

    public function getDsn(): DsnDto
    {
        return $this->dsn;
    }

    public function getPlatform(): ?PlatformInterface
    {
        return $this->platform;
    }

    public function setPlatform(PlatformInterface $platform): void
    {
        $this->platform = $platform;
    }
}
