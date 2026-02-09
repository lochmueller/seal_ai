<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Factory;

use Lochmueller\Seal\Dto\DsnDto;
use Symfony\AI\Store\Bridge\ClickHouse\Store as ClickHouseStore;
use Symfony\AI\Store\Bridge\Cloudflare\Store as CloudflareStore;
use Symfony\AI\Store\Bridge\Elasticsearch\Store as ElasticsearchStore;
use Symfony\AI\Store\Bridge\ManticoreSearch\Store as ManticoreSearchStore;
use Symfony\AI\Store\Bridge\MariaDb\Store as MariaDbStore;
use Symfony\AI\Store\Bridge\Meilisearch\Store as MeilisearchStore;
use Symfony\AI\Store\Bridge\Milvus\Store as MilvusStore;
use Symfony\AI\Store\Bridge\MongoDb\Store as MongoDbStore;
use Symfony\AI\Store\Bridge\Neo4j\Store as Neo4jStore;
use Symfony\AI\Store\Bridge\OpenSearch\Store as OpenSearchStore;
use Symfony\AI\Store\Bridge\Pinecone\Store as PineconeStore;
use Symfony\AI\Store\Bridge\Postgres\Store as PostgresStore;
use Symfony\AI\Store\Bridge\Qdrant\Store as QdrantStore;
use Symfony\AI\Store\Bridge\Redis\Store as RedisStore;
use Symfony\AI\Store\Bridge\SurrealDb\Store as SurrealDbStore;
use Symfony\AI\Store\Bridge\Typesense\Store as TypesenseStore;
use Symfony\AI\Store\Bridge\Weaviate\Store as WeaviateStore;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\HttpClient\HttpClient;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Lochmueller\SealAi\Event\StoreFactoryEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

class StoreFactory
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function fromDsn(DsnDto $dsn): StoreInterface&ManagedStoreInterface
    {
        $client = HttpClient::create();

        // DSN Examples:
        // mariadb://default?tableName=my_table
        // postgres://default?tableName=my_table
        // qdrant://api-key@host:6333?collectionName=my_collection
        // elasticsearch://host:9200?indexName=my_index
        // opensearch://host:9200?indexName=my_index
        // meilisearch://api-key@host:7700?indexName=my_index
        // milvus://api-key@host:19530?database=my_db&collection=my_collection
        // redis://host:6379?indexName=my_index
        // weaviate://api-key@host:8080?collection=my_collection
        // typesense://api-key@host:8108?collection=my_collection
        // neo4j://user:password@host:7474?databaseName=neo4j&vectorIndexName=my_index&nodeName=Document
        // cloudflare://api-key@default?accountId=my_account&index=my_index
        // pinecone://api-key@default?indexName=my_index
        // mongodb://host:27017?databaseName=my_db&collectionName=my_collection&indexName=my_index
        // surrealdb://user:password@host:8000?namespace=my_ns&database=my_db
        // manticore://host:9308?table=my_table
        // clickhouse://host:8123?databaseName=my_db&tableName=my_table

        switch ($dsn->scheme) {
            case 'event':
                $event = $this->eventDispatcher->dispatch(new StoreFactoryEvent($dsn));
                return $event->getStore() ?? throw new \RuntimeException('No store provided by event listener for DSN scheme "event"', 1739091201);

            case 'mariadb':
                class_exists(MariaDbStore::class) or throw new \RuntimeException('Please install symfony/ai-maria-db-store to use MariaDB store');
                $typo3Connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');
                $tableName = $dsn->query['tableName'] ?? 'tx_sealai_data';
                $indexName = $dsn->query['indexName'] ?? 'embedding';
                $vectorFieldName = $dsn->query['vectorFieldName'] ?? 'embedding';
                return MariaDbStore::fromDbal($typo3Connection, $tableName, $indexName, $vectorFieldName);

            case 'postgres':
            case 'postgresql':
                class_exists(PostgresStore::class) or throw new \RuntimeException('Please install symfony/ai-postgres-store to use PostgreSQL store');
                $typo3Connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');
                $tableName = $dsn->query['tableName'] ?? 'tx_sealai_data';
                $vectorFieldName = $dsn->query['vectorFieldName'] ?? 'embedding';
                return PostgresStore::fromDbal($typo3Connection, $tableName, $vectorFieldName);

            case 'qdrant':
                class_exists(QdrantStore::class) or throw new \RuntimeException('Please install symfony/ai-qdrant-store to use Qdrant store');
                $endpointUrl = $this->buildEndpointUrl($dsn, 'http', 6333);
                $apiKey = $dsn->user ?? '';
                $collectionName = $dsn->query['collectionName'] ?? 'default';
                $dimensions = (int) ($dsn->query['dimensions'] ?? 1536);
                return new QdrantStore($client, $endpointUrl, $apiKey, $collectionName, $dimensions);

            case 'elasticsearch':
                class_exists(ElasticsearchStore::class) or throw new \RuntimeException('Please install symfony/ai-elasticsearch-store to use Elasticsearch store');
                $endpoint = $this->buildEndpointUrl($dsn, 'http', 9200);
                $indexName = $dsn->query['indexName'] ?? 'default';
                return new ElasticsearchStore($client, $endpoint, $indexName);

            case 'opensearch':
                class_exists(OpenSearchStore::class) or throw new \RuntimeException('Please install symfony/ai-open-search-store to use OpenSearch store');
                $endpoint = $this->buildEndpointUrl($dsn, 'http', 9200);
                $indexName = $dsn->query['indexName'] ?? 'default';
                return new OpenSearchStore($client, $endpoint, $indexName);

            case 'meilisearch':
                class_exists(MeilisearchStore::class) or throw new \RuntimeException('Please install symfony/ai-meilisearch-store to use Meilisearch store');
                $endpointUrl = $this->buildEndpointUrl($dsn, 'http', 7700);
                $apiKey = $dsn->user ?? '';
                $indexName = $dsn->query['indexName'] ?? 'default';
                return new MeilisearchStore($client, $endpointUrl, $apiKey, $indexName);

            case 'milvus':
                class_exists(MilvusStore::class) or throw new \RuntimeException('Please install symfony/ai-milvus-store to use Milvus store');
                $endpointUrl = $this->buildEndpointUrl($dsn, 'http', 19530);
                $apiKey = $dsn->user ?? '';
                $database = $dsn->query['database'] ?? 'default';
                $collection = $dsn->query['collection'] ?? 'default';
                return new MilvusStore($client, $endpointUrl, $apiKey, $database, $collection);

            case 'redis':
                class_exists(RedisStore::class) or throw new \RuntimeException('Please install symfony/ai-redis-store to use Redis store');
                $redis = new \Redis();
                $host = $dsn->host ?? 'localhost';
                $port = $dsn->port ?? 6379;
                $redis->connect($host, $port);
                if ($dsn->user) {
                    $redis->auth($dsn->user);
                }
                $indexName = $dsn->query['indexName'] ?? 'default';
                return new RedisStore($redis, $indexName);

            case 'weaviate':
                class_exists(WeaviateStore::class) or throw new \RuntimeException('Please install symfony/ai-weaviate-store to use Weaviate store');
                $endpointUrl = $this->buildEndpointUrl($dsn, 'http', 8080);
                $apiKey = $dsn->user ?? '';
                $collection = $dsn->query['collection'] ?? 'default';
                return new WeaviateStore($client, $endpointUrl, $apiKey, $collection);

            case 'typesense':
                class_exists(TypesenseStore::class) or throw new \RuntimeException('Please install symfony/ai-typesense-store to use Typesense store');
                $endpointUrl = $this->buildEndpointUrl($dsn, 'http', 8108);
                $apiKey = $dsn->user ?? '';
                $collection = $dsn->query['collection'] ?? 'default';
                return new TypesenseStore($client, $endpointUrl, $apiKey, $collection);

            case 'neo4j':
                class_exists(Neo4jStore::class) or throw new \RuntimeException('Please install symfony/ai-neo4j-store to use Neo4j store');
                $endpointUrl = $this->buildEndpointUrl($dsn, 'http', 7474);
                $username = $dsn->user ?? 'neo4j';
                $password = $dsn->query['password'] ?? '';
                $databaseName = $dsn->query['databaseName'] ?? 'neo4j';
                $vectorIndexName = $dsn->query['vectorIndexName'] ?? 'default';
                $nodeName = $dsn->query['nodeName'] ?? 'Document';
                return new Neo4jStore($client, $endpointUrl, $username, $password, $databaseName, $vectorIndexName, $nodeName);

            case 'cloudflare':
                class_exists(CloudflareStore::class) or throw new \RuntimeException('Please install symfony/ai-cloudflare-store to use Cloudflare store');
                $accountId = $dsn->query['accountId'] ?? '';
                $apiKey = $dsn->user ?? '';
                $index = $dsn->query['index'] ?? 'default';
                return new CloudflareStore($client, $accountId, $apiKey, $index);

            case 'pinecone':
                class_exists(PineconeStore::class) or throw new \RuntimeException('Please install symfony/ai-pinecone-store to use Pinecone store');
                $apiKey = $dsn->user ?? '';
                $pinecone = new \Probots\Pinecone\Client($apiKey);
                $indexName = $dsn->query['indexName'] ?? 'default';
                return new PineconeStore($pinecone, $indexName);

            case 'mongodb':
                class_exists(MongoDbStore::class) or throw new \RuntimeException('Please install symfony/ai-mongo-db-store to use MongoDB store');
                $mongoUrl = $this->buildEndpointUrl($dsn, 'mongodb', 27017);
                $mongoClient = new \MongoDB\Client($mongoUrl);
                $databaseName = $dsn->query['databaseName'] ?? 'default';
                $collectionName = $dsn->query['collectionName'] ?? 'default';
                $indexName = $dsn->query['indexName'] ?? 'default';
                return new MongoDbStore($mongoClient, $databaseName, $collectionName, $indexName);

            case 'surrealdb':
                class_exists(SurrealDbStore::class) or throw new \RuntimeException('Please install symfony/ai-surreal-db-store to use SurrealDB store');
                $endpointUrl = $this->buildEndpointUrl($dsn, 'http', 8000);
                $user = $dsn->user ?? 'root';
                $password = $dsn->query['password'] ?? 'root';
                $namespace = $dsn->query['namespace'] ?? 'default';
                $database = $dsn->query['database'] ?? 'default';
                return new SurrealDbStore($client, $endpointUrl, $user, $password, $namespace, $database);

            case 'manticore':
                class_exists(ManticoreSearchStore::class) or throw new \RuntimeException('Please install symfony/ai-manticore-search-store to use ManticoreSearch store');
                $host = $this->buildEndpointUrl($dsn, 'http', 9308);
                $table = $dsn->query['table'] ?? 'default';
                return new ManticoreSearchStore($client, $host, $table);

            case 'clickhouse':
                class_exists(ClickHouseStore::class) or throw new \RuntimeException('Please install symfony/ai-click-house-store to use ClickHouse store');
                $databaseName = $dsn->query['databaseName'] ?? 'default';
                $tableName = $dsn->query['tableName'] ?? 'embedding';
                return new ClickHouseStore($client, $databaseName, $tableName);

            default:
                throw new \InvalidArgumentException("Unsupported store DSN scheme: {$dsn->scheme}");
        }
    }

    private function buildEndpointUrl(DsnDto $dsn, string $defaultScheme, int $defaultPort): string
    {
        $host = $dsn->host ?? 'localhost';
        $port = $dsn->port ?? $defaultPort;

        return "{$defaultScheme}://{$host}:{$port}";
    }
}
