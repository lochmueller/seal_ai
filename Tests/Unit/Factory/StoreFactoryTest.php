<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Factory;

use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\SealAi\Event\CreateStoreEvent;
use Lochmueller\SealAi\Factory\StoreFactory;
use Lochmueller\SealAi\Store\UnmanagedStoreDecorator;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Store\Bridge\Cache\Store as CacheStore;
use Symfony\AI\Store\Bridge\ClickHouse\Store as ClickHouseStore;
use Symfony\AI\Store\Bridge\Cloudflare\Store as CloudflareStore;
use Symfony\AI\Store\Bridge\Elasticsearch\Store as ElasticsearchStore;
use Symfony\AI\Store\Bridge\ManticoreSearch\Store as ManticoreSearchStore;
use Symfony\AI\Store\Bridge\MariaDb\Store as MariaDbStore;
use Symfony\AI\Store\Bridge\Meilisearch\Store as MeilisearchStore;
use Symfony\AI\Store\Bridge\Milvus\Store as MilvusStore;
use Symfony\AI\Store\Bridge\Neo4j\Store as Neo4jStore;
use Symfony\AI\Store\Bridge\OpenSearch\Store as OpenSearchStore;
use Symfony\AI\Store\Bridge\Postgres\Store as PostgresStore;
use Symfony\AI\Store\Bridge\Qdrant\Store as QdrantStore;
use Symfony\AI\Store\Bridge\S3Vectors\Store as S3VectorsStore;
use Symfony\AI\Store\Bridge\Sqlite\Store as SqliteStore;
use Symfony\AI\Store\Bridge\SurrealDb\Store as SurrealDbStore;
use Symfony\AI\Store\Bridge\Typesense\Store as TypesenseStore;
use Symfony\AI\Store\Bridge\Vektor\Store as VektorStore;
use Symfony\AI\Store\Bridge\Weaviate\Store as WeaviateStore;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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

    /**
     * Schemes whose stores can be created without a running backend (HTTP clients connect lazily).
     *
     * @return iterable<string, array{DsnDto, class-string}>
     */
    public static function storeDsnProvider(): iterable
    {
        yield 'qdrant' => [new DsnDto(scheme: 'qdrant', user: 'api-key', host: 'localhost', port: 6333, query: ['collectionName' => 'col', 'dimensions' => '768']), QdrantStore::class];
        yield 'elasticsearch' => [new DsnDto(scheme: 'elasticsearch', host: 'localhost', port: 9200, query: ['indexName' => 'idx']), ElasticsearchStore::class];
        yield 'opensearch' => [new DsnDto(scheme: 'opensearch', host: 'localhost', query: ['indexName' => 'idx']), OpenSearchStore::class];
        yield 'meilisearch' => [new DsnDto(scheme: 'meilisearch', user: 'api-key', host: 'localhost', port: 7700), MeilisearchStore::class];
        yield 'milvus' => [new DsnDto(scheme: 'milvus', host: 'localhost', query: ['database' => 'db', 'collection' => 'col']), MilvusStore::class];
        yield 'weaviate' => [new DsnDto(scheme: 'weaviate', user: 'api-key', host: 'localhost', port: 8080), WeaviateStore::class];
        yield 'typesense' => [new DsnDto(scheme: 'typesense', user: 'api-key', host: 'localhost', port: 8108), TypesenseStore::class];
        yield 'neo4j' => [new DsnDto(scheme: 'neo4j', user: 'neo4j', pass: 'secret', host: 'localhost', port: 7474), Neo4jStore::class];
        yield 'cloudflare' => [new DsnDto(scheme: 'cloudflare', user: 'api-key', host: 'default', query: ['accountId' => 'acc', 'index' => 'idx']), CloudflareStore::class];
        yield 'surrealdb' => [new DsnDto(scheme: 'surrealdb', user: 'root', pass: 'root', host: 'localhost', port: 8000), SurrealDbStore::class];
        yield 'manticore' => [new DsnDto(scheme: 'manticore', host: 'localhost', port: 9308, query: ['table' => 'tbl']), ManticoreSearchStore::class];
        yield 'clickhouse' => [new DsnDto(scheme: 'clickhouse', user: 'default', pass: 'p@ss', host: 'localhost', port: 8123), ClickHouseStore::class];
        yield 'clickhouse without credentials' => [new DsnDto(scheme: 'clickhouse'), ClickHouseStore::class];
        yield 's3vectors' => [new DsnDto(scheme: 's3vectors', user: 'eu-central-1', host: 'default', query: ['vectorBucketName' => 'bucket']), S3VectorsStore::class];
        yield 'azuresearch alias' => [new DsnDto(scheme: 'azuresearch', host: 'my-service.search.windows.net'), UnmanagedStoreDecorator::class];
    }

    /**
     * @param class-string $expectedClass
     */
    #[DataProvider('storeDsnProvider')]
    public function testSchemeCreatesStore(DsnDto $dsn, string $expectedClass): void
    {
        $factory = new StoreFactory($this->createStub(EventDispatcherInterface::class));

        $store = $factory->fromDsn($dsn);

        self::assertInstanceOf($expectedClass, $store);
        self::assertInstanceOf(StoreInterface::class, $store);
        self::assertInstanceOf(ManagedStoreInterface::class, $store);
    }

    public function testCacheSchemeCreatesStoreInGivenPath(): void
    {
        $dsn = new DsnDto(scheme: 'cache', host: 'default', path: sys_get_temp_dir() . '/seal-ai-cache-' . uniqid(), query: ['key' => 'vectors', 'strategy' => 'cosine']);

        $factory = new StoreFactory($this->createStub(EventDispatcherInterface::class));

        self::assertInstanceOf(CacheStore::class, $factory->fromDsn($dsn));
    }

    public function testVektorSchemeCreatesStoreInGivenPath(): void
    {
        $dsn = new DsnDto(scheme: 'vektor', host: 'default', path: sys_get_temp_dir() . '/seal-ai-vektor-' . uniqid(), query: ['dimensions' => '3']);

        $factory = new StoreFactory($this->createStub(EventDispatcherInterface::class));

        self::assertInstanceOf(VektorStore::class, $factory->fromDsn($dsn));
    }

    public function testSqliteSchemeUsesDefaultDatabasePathAndTableName(): void
    {
        $databaseFile = Environment::getProjectPath() . '/var/seal-ai.sqlite';
        $existedBefore = file_exists($databaseFile);
        @mkdir(\dirname($databaseFile), 0o777, true);

        $factory = new StoreFactory($this->createStub(EventDispatcherInterface::class));

        try {
            self::assertInstanceOf(SqliteStore::class, $factory->fromDsn(new DsnDto(scheme: 'sqlite')));
        } finally {
            if (!$existedBefore) {
                @unlink($databaseFile);
            }
        }
    }

    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function dbalSchemeProvider(): iterable
    {
        yield 'mariadb' => ['mariadb', MariaDbStore::class];
        yield 'postgres' => ['postgres', PostgresStore::class];
        yield 'postgresql' => ['postgresql', PostgresStore::class];
    }

    /**
     * @param class-string $expectedClass
     */
    #[DataProvider('dbalSchemeProvider')]
    public function testDbalSchemeUsesTypo3Connection(string $scheme, string $expectedClass): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getNativeConnection')->willReturn(new \PDO('sqlite::memory:'));

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->expects(self::once())
            ->method('getConnectionForTable')
            ->with('tt_content')
            ->willReturn($connection);
        GeneralUtility::addInstance(ConnectionPool::class, $connectionPool);

        $factory = new StoreFactory($this->createStub(EventDispatcherInterface::class));

        self::assertInstanceOf($expectedClass, $factory->fromDsn(new DsnDto(scheme: $scheme, host: 'default', query: ['tableName' => 'my_table'])));
    }
}
