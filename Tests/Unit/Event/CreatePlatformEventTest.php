<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Event;

use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\SealAi\Event\CreatePlatformEvent;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Symfony\AI\Platform\PlatformInterface;

class CreatePlatformEventTest extends AbstractTest
{
    public function testEventHasNoPlatformByDefault(): void
    {
        $dsn = new DsnDto(scheme: 'custom', host: 'default');
        $event = new CreatePlatformEvent($dsn);

        self::assertSame($dsn, $event->getDsn());
        self::assertNull($event->getPlatform());
    }

    public function testSetPlatformStoresPlatform(): void
    {
        $platform = $this->createStub(PlatformInterface::class);
        $event = new CreatePlatformEvent(new DsnDto(scheme: 'custom'));

        $event->setPlatform($platform);

        self::assertSame($platform, $event->getPlatform());
    }
}
