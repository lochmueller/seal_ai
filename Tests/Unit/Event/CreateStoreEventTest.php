<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Event;

use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\SealAi\Event\CreateStoreEvent;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;

class CreateStoreEventTest extends AbstractTest
{
    public function testEventHasNoStoreByDefault(): void
    {
        $dsn = new DsnDto(scheme: 'custom', host: 'default');
        $event = new CreateStoreEvent($dsn);

        self::assertSame($dsn, $event->getDsn());
        self::assertNull($event->getStore());
    }

    public function testSetStoreStoresStore(): void
    {
        $store = $this->getStore();
        $event = new CreateStoreEvent(new DsnDto(scheme: 'custom'));

        $event->setStore($store);

        self::assertSame($store, $event->getStore());
    }
}
