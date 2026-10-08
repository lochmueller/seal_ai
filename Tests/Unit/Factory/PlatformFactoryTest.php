<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Factory;

use Lochmueller\Seal\DsnParser;
use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\SealAi\Event\CreatePlatformEvent;
use Lochmueller\SealAi\Factory\PlatformFactory;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Bridge\Failover\FailoverPlatform;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;

class PlatformFactoryTest extends AbstractTest
{
    public function testEventSchemeDispatchesEventAndReturnsPlatform(): void
    {
        $dsn = new DsnDto(scheme: 'event');
        $platform = $this->createStub(PlatformInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn(CreatePlatformEvent $event): bool => $event->getDsn() === $dsn))
            ->willReturnCallback(static function (CreatePlatformEvent $event) use ($platform): CreatePlatformEvent {
                $event->setPlatform($platform);
                return $event;
            });

        $factory = new PlatformFactory($eventDispatcher, $this->createDsnParser());
        $result = $factory->fromDsn($dsn);

        self::assertSame($platform, $result);
    }

    public function testEventSchemeThrowsWhenNoPlatformProvided(): void
    {
        $dsn = new DsnDto(scheme: 'event');

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(static fn(CreatePlatformEvent $event): CreatePlatformEvent => $event);

        $factory = new PlatformFactory($eventDispatcher, $this->createDsnParser());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No platform provided by event listener for DSN scheme "event"');
        $factory->fromDsn($dsn);
    }

    public function testUnsupportedSchemeThrowsInvalidArgumentException(): void
    {
        $dsn = new DsnDto(scheme: 'unsupported-scheme');

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $factory = new PlatformFactory($eventDispatcher, $this->createDsnParser());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported DSN scheme: unsupported-scheme');
        $factory->fromDsn($dsn);
    }

    public function testFailoverSchemeCreatesFailoverPlatform(): void
    {
        $dsn = $this->createDsnParser()->parse(
            'failover://default?platforms[]=openai://sk-test@default&platforms[]=' . urlencode('ollama://localhost:11434?foo=bar') . '&model=text-embedding-3-small',
        );

        $factory = new PlatformFactory($this->createStub(EventDispatcherInterface::class), $this->createDsnParser());

        self::assertInstanceOf(FailoverPlatform::class, $factory->fromDsn($dsn));
    }

    public function testFailoverSchemeThrowsWithoutPlatforms(): void
    {
        $dsn = new DsnDto(scheme: 'failover', host: 'default');

        $factory = new PlatformFactory($this->createStub(EventDispatcherInterface::class), $this->createDsnParser());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1759312800);
        $factory->fromDsn($dsn);
    }

    public function testFailoverSchemePropagatesInvalidNestedDsn(): void
    {
        $dsn = new DsnDto(scheme: 'failover', query: ['platforms' => ['unsupported-scheme://default']]);

        $factory = new PlatformFactory($this->createStub(EventDispatcherInterface::class), $this->createDsnParser());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported DSN scheme: unsupported-scheme');
        $factory->fromDsn($dsn);
    }

    public function testCohereSchemeCreatesPlatform(): void
    {
        $dsn = $this->createDsnParser()->parse('cohere://api-key@default');

        $factory = new PlatformFactory($this->createStub(EventDispatcherInterface::class), $this->createDsnParser());

        self::assertInstanceOf(Platform::class, $factory->fromDsn($dsn));
    }

    private function createDsnParser(): DsnParser
    {
        return new DsnParser($this->createStub(EventDispatcherInterface::class));
    }
}
