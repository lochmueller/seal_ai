<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Factory;

use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\SealAi\Event\StoreFactoryEvent;
use Lochmueller\SealAi\Factory\StoreFactory;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;

interface TestManagedStoreInterface extends StoreInterface, ManagedStoreInterface {}

class StoreFactoryTest extends AbstractTest
{
    public function testEventSchemeDispatchesEventAndReturnsStore(): void
    {
        $dsn = new DsnDto(scheme: 'event');
        $store = $this->createStub(TestManagedStoreInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn(StoreFactoryEvent $event): bool => $event->getDsn() === $dsn))
            ->willReturnCallback(static function (StoreFactoryEvent $event) use ($store): StoreFactoryEvent {
                $event->setStore($store);
                return $event;
            });

        $factory = new StoreFactory($eventDispatcher);
        $result = $factory->fromDsn($dsn);

        self::assertSame($store, $result);
    }

    public function testEventSchemeThrowsWhenNoStoreProvided(): void
    {
        $dsn = new DsnDto(scheme: 'event');

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(static fn(StoreFactoryEvent $event): StoreFactoryEvent => $event);

        $factory = new StoreFactory($eventDispatcher);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No store provided by event listener for DSN scheme "event"');
        $factory->fromDsn($dsn);
    }

    public function testUnsupportedSchemeThrowsInvalidArgumentException(): void
    {
        $dsn = new DsnDto(scheme: 'unsupported-scheme');

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $factory = new StoreFactory($eventDispatcher);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported store DSN scheme: unsupported-scheme');
        $factory->fromDsn($dsn);
    }
}
