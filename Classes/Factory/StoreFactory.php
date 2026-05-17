<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Factory;

use Lochmueller\Seal\Dto\DsnDto;
use Symfony\AI\Store\Bridge\ClickHouse as ClickHouseBridge;
use Symfony\AI\Store\Bridge\Cloudflare as CloudflareBridge;
use Symfony\AI\Store\Bridge\Elasticsearch as ElasticsearchBridge;
use Symfony\AI\Store\Bridge\ManticoreSearch as ManticoreSearchBridge;
use Symfony\AI\Store\Bridge\MariaDb as MariaDbBridge;
use Symfony\AI\Store\Bridge\Meilisearch as MeilisearchBridge;
use Symfony\AI\Store\Bridge\Milvus as MilvusBridge;
use Symfony\AI\Store\Bridge\MongoDb as MongoDbBridge;
use Symfony\AI\Store\Bridge\Neo4j as Neo4jBridge;
use Symfony\AI\Store\Bridge\OpenSearch as OpenSearchBridge;
use Symfony\AI\Store\Bridge\Pinecone as PineconeBridge;
use Symfony\AI\Store\Bridge\Postgres as PostgresBridge;
use Symfony\AI\Store\Bridge\Qdrant as QdrantBridge;
use Symfony\AI\Store\Bridge\Redis as RedisBridge;
use Symfony\AI\Store\Bridge\S3Vectors as S3VectorsBridge;
use Symfony\AI\Store\Bridge\SurrealDb as SurrealDbBridge;
use Symfony\AI\Store\Bridge\Typesense as TypesenseBridge;
use Symfony\AI\Store\Bridge\Vektor as VektorBridge;
use Symfony\AI\Store\Bridge\Weaviate as WeaviateBridge;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\HttpClient\HttpClient;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Lochmueller\SealAi\Event\CreateStoreEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @see https://github.com/symfony/ai/issues/402
 */
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
        // vektor://default/path/to/storage?dimensions=1536
        // s3vectors://region@default?vectorBucketName=my_bucket&indexName=my_index

        switch ($dsn->scheme) {
            case 'event':
                $event = $this->eventDispatcher->dispatch(new CreateStoreEvent($dsn));
                return $event->getStore() ?? throw new \RuntimeException('No store provided by event listener for DSN scheme "event"', 1739091201);

            case 'mariadb':
                class_exists(MariaDbBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-maria-db-store to use MariaDB store');
                $typo3Connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');
                $tableName = $dsn->query['tableName'] ?? 'tx_sealai_data';
                $indexName = $dsn->query['indexName'] ?? 'embedding';
                $vectorFieldName = $dsn->query['vectorFieldName'] ?? 'embedding';
                return MariaDbBridge\Store::fromDbal($typo3Connection, $tableName, $indexName, $vectorFieldName);

            case 'postgres':
            case 'postgresql':
                class_exists(PostgresBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-postgres-store to use PostgreSQL store');
                $typo3Connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');
                $tableName = $dsn->query['tableName'] ?? 'tx_sealai_data';
                $vectorFieldName = $dsn->query['vectorFieldName'] ?? 'embedding';
                return PostgresBridge\StoreFactory::createStoreFromDbal($typo3Connection, $tableName, $vectorFieldName);

            case 'qdrant':
                class_exists(QdrantBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-qdrant-store to use Qdrant store');
                $endpointUrl = $this->buildEndpointUrl($dsn, 'http', 6333);
                $collectionName = $dsn->query['collectionName'] ?? 'default';
                $dimensions = (int) ($dsn->query['dimensions'] ?? 1536);
                return QdrantBridge\StoreFactory::create($collectionName, $endpointUrl, $dsn->user ?: null, null, $dimensions);

            case 'elasticsearch':
                class_exists(ElasticsearchBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-elasticsearch-store to use Elasticsearch store');
                $endpoint = $this->buildEndpointUrl($dsn, 'http', 9200);
                $indexName = $dsn->query['indexName'] ?? 'default';
                return new ElasticsearchBridge\Store($client, $endpoint, $indexName);

            case 'opensearch':
                class_exists(OpenSearchBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-open-search-store to use OpenSearch store');
                $endpoint = $this->buildEndpointUrl($dsn, 'http', 9200);
                $indexName = $dsn->query['indexName'] ?? 'default';
                return new OpenSearchBridge\Store($client, $endpoint, $indexName);

            case 'meilisearch':
                class_exists(MeilisearchBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-meilisearch-store to use Meilisearch store');
                $endpointUrl = $this->buildEndpointUrl($dsn, 'http', 7700);
                $indexName = $dsn->query['indexName'] ?? 'default';
                return MeilisearchBridge\StoreFactory::create($indexName, $endpointUrl, $dsn->user ?: null);

            case 'milvus':
                class_exists(MilvusBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-milvus-store to use Milvus store');
                $endpointUrl = $this->buildEndpointUrl($dsn, 'http', 19530);
                $apiKey = $dsn->user ?? '';
                $database = $dsn->query['database'] ?? 'default';
                $collection = $dsn->query['collection'] ?? 'default';
                return new MilvusBridge\Store($client, $endpointUrl, $apiKey, $database, $collection);

            case 'redis':
                class_exists(RedisBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-redis-store to use Redis store');
                $redis = new \Redis();
                $host = $dsn->host ?? 'localhost';
                $port = $dsn->port ?? 6379;
                $redis->connect($host, $port);
                if ($dsn->user) {
                    $redis->auth($dsn->user);
                }
                $indexName = $dsn->query['indexName'] ?? 'default';
                return new RedisBridge\Store($redis, $indexName);

            case 'weaviate':
                class_exists(WeaviateBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-weaviate-store to use Weaviate store');
                $endpointUrl = $this->buildEndpointUrl($dsn, 'http', 8080);
                $collection = $dsn->query['collection'] ?? 'default';
                return WeaviateBridge\StoreFactory::create($collection, $endpointUrl, $dsn->user ?: null);

            case 'typesense':
                class_exists(TypesenseBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-typesense-store to use Typesense store');
                $endpointUrl = $this->buildEndpointUrl($dsn, 'http', 8108);
                $apiKey = $dsn->user ?? '';
                $collection = $dsn->query['collection'] ?? 'default';
                return new TypesenseBridge\Store($client, $endpointUrl, $apiKey, $collection);

            case 'neo4j':
                class_exists(Neo4jBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-neo4j-store to use Neo4j store');
                $endpointUrl = $this->buildEndpointUrl($dsn, 'http', 7474);
                $username = $dsn->user ?? 'neo4j';
                $password = $dsn->pass ?? '';
                $databaseName = $dsn->query['databaseName'] ?? 'neo4j';
                $vectorIndexName = $dsn->query['vectorIndexName'] ?? 'default';
                $nodeName = $dsn->query['nodeName'] ?? 'Document';
                return new Neo4jBridge\Store($client, $endpointUrl, $username, $password, $databaseName, $vectorIndexName, $nodeName);

            case 'cloudflare':
                class_exists(CloudflareBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-cloudflare-store to use Cloudflare store');
                $accountId = $dsn->query['accountId'] ?? '';
                $apiKey = $dsn->user ?? '';
                $index = $dsn->query['index'] ?? 'default';
                return new CloudflareBridge\Store($client, $accountId, $apiKey, $index);

            case 'pinecone':
                class_exists(PineconeBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-pinecone-store to use Pinecone store');
                $apiKey = $dsn->user ?? '';
                $pinecone = new \Probots\Pinecone\Client($apiKey);
                $indexName = $dsn->query['indexName'] ?? 'default';
                return new PineconeBridge\Store($pinecone, $indexName);

            case 'mongodb':
                class_exists(MongoDbBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-mongo-db-store to use MongoDB store');
                $mongoUrl = $this->buildEndpointUrl($dsn, 'mongodb', 27017);
                $mongoClient = new \MongoDB\Client($mongoUrl);
                $databaseName = $dsn->query['databaseName'] ?? 'default';
                $collectionName = $dsn->query['collectionName'] ?? 'default';
                $indexName = $dsn->query['indexName'] ?? 'default';
                return new MongoDbBridge\Store($mongoClient, $databaseName, $collectionName, $indexName);

            case 'surrealdb':
                class_exists(SurrealDbBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-surreal-db-store to use SurrealDB store');
                $endpointUrl = $this->buildEndpointUrl($dsn, 'http', 8000);
                $user = $dsn->user ?? 'root';
                $password = $dsn->pass ?? 'root';
                $namespace = $dsn->query['namespace'] ?? 'default';
                $database = $dsn->query['database'] ?? 'default';
                return new SurrealDbBridge\Store($client, $endpointUrl, $user, $password, $namespace, $database);

            case 'manticore':
                class_exists(ManticoreSearchBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-manticore-search-store to use ManticoreSearch store');
                $host = $this->buildEndpointUrl($dsn, 'http', 9308);
                $table = $dsn->query['table'] ?? 'default';
                return new ManticoreSearchBridge\Store($client, $host, $table);

            case 'clickhouse':
                class_exists(ClickHouseBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-click-house-store to use ClickHouse store');
                $databaseName = $dsn->query['databaseName'] ?? 'default';
                $tableName = $dsn->query['tableName'] ?? 'embedding';
                return new ClickHouseBridge\Store($client, $databaseName, $tableName);

            case 'vektor':
                class_exists(VektorBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-vektor-store to use Vektor store');
                $storagePath = $dsn->path ?? Environment::getVarPath() . '/seal-ai-vektor';
                $dimensions = (int) ($dsn->query['dimensions'] ?? 1536);
                return new VektorBridge\Store($storagePath, $dimensions);

            case 's3vectors':
                class_exists(S3VectorsBridge\Store::class) or throw new \RuntimeException('Please install symfony/ai-s3vectors-store to use S3 Vectors store');
                $region = $dsn->user ?? 'us-east-1';
                $s3VectorsClient = new \AsyncAws\S3Vectors\S3VectorsClient(['region' => $region]);
                $vectorBucketName = $dsn->query['vectorBucketName'] ?? 'default';
                $indexName = $dsn->query['indexName'] ?? 'default';
                return new S3VectorsBridge\Store($s3VectorsClient, $vectorBucketName, $indexName);

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
