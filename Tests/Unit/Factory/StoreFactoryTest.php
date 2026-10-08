<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Factory;

use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\SealAi\Event\CreateStoreEvent;
use Lochmueller\SealAi\Factory\StoreFactory;
use Lochmueller\SealAi\Store\UnmanagedStoreDecorator;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Store\Bridge\Sqlite\Store as SqliteStore;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use TYPO3\CMS\Core\Core\Environment;

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
            ->with(self::callback(static fn(CreateStoreEvent $event): bool => $event->getDsn() === $dsn))
            ->willReturnCallback(static function (CreateStoreEvent $event) use ($store): CreateStoreEvent {
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
            ->willReturnCallback(static fn(CreateStoreEvent $event): CreateStoreEvent => $event);

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

    public function testSqliteSchemeCreatesStoreRelativeToProjectPath(): void
    {
        $databaseFile = 'seal-ai-test-' . uniqid() . '.sqlite';
        $dsn = new DsnDto(scheme: 'sqlite', host: 'default', path: $databaseFile, query: ['tableName' => 'test_table']);

        $factory = new StoreFactory($this->createStub(EventDispatcherInterface::class));
        $store = $factory->fromDsn($dsn);

        try {
            self::assertInstanceOf(SqliteStore::class, $store);
            self::assertFileExists(Environment::getProjectPath() . '/' . $databaseFile);
        } finally {
            @unlink(Environment::getProjectPath() . '/' . $databaseFile);
        }
    }

    public function testAzureSearchSchemeCreatesUnmanagedStore(): void
    {
        $dsn = new DsnDto(scheme: 'azure-search', user: 'api-key', host: 'my-service.search.windows.net', query: ['indexName' => 'idx']);

        $factory = new StoreFactory($this->createStub(EventDispatcherInterface::class));

        self::assertInstanceOf(UnmanagedStoreDecorator::class, $factory->fromDsn($dsn));
    }

    public function testAzureSearchSchemeThrowsWithoutHost(): void
    {
        $factory = new StoreFactory($this->createStub(EventDispatcherInterface::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1791446401);
        $factory->fromDsn(new DsnDto(scheme: 'azure-search'));
    }

    public function testSupabaseSchemeCreatesUnmanagedStore(): void
    {
        $dsn = new DsnDto(scheme: 'supabase', user: 'api-key', host: 'my-project.supabase.co');

        $factory = new StoreFactory($this->createStub(EventDispatcherInterface::class));

        self::assertInstanceOf(UnmanagedStoreDecorator::class, $factory->fromDsn($dsn));
    }

    public function testSupabaseSchemeThrowsWithoutHost(): void
    {
        $factory = new StoreFactory($this->createStub(EventDispatcherInterface::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1791446402);
        $factory->fromDsn(new DsnDto(scheme: 'supabase'));
    }
}
