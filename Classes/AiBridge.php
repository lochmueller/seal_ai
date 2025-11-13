<?php

declare(strict_types=1);

namespace Lochmueller\SealAi;

use Symfony\AI\Platform\Bridge\Gemini\Embeddings;
use Symfony\AI\Platform\Bridge\Gemini\Embeddings\TaskType;
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory;
use Symfony\AI\Store\Bridge\MariaDb\Store;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\HttpClient\CurlHttpClient;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AiBridge
{
    protected StoreInterface&ManagedStoreInterface $store;

    protected Vectorizer $vectorizer;

    protected array $dsn;

    public function __construct()
    {
    }

    public function getStore(): StoreInterface&ManagedStoreInterface
    {
        return $this->store;
    }

    public function getVectorizer(): Vectorizer
    {
        return $this->vectorizer;
    }

    public function initialize(array $dsn)
    {
        $this->dsn = $dsn;
        $this->initializeStore();
        $this->initializeVectorizer();
    }

    public function initializeStore(): void
    {
        // @todo Switch to DSN
        $typo3Connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');
        $this->store = Store::fromDbal(
            connection: $typo3Connection,
            tableName: $this->getTableName(),
        );
        $this->store->setup(['dimensions' => $this->getDimensions()]);
    }

    public function initializeVectorizer(): void
    {
        // @todo Switch to Platform
        $platform = PlatformFactory::create($this->getApiKey(), new CurlHttpClient());
        $embeddings = new Embeddings(Embeddings::TEXT_EMBEDDING_004, options: ['dimensions' => $this->getDimensions(), 'task_type' => TaskType::SemanticSimilarity]);
        $this->vectorizer = new Vectorizer($platform, $embeddings);
    }

    public function getTableName(): string
    {
        $suffix = isset($this->dsn['query']['tableNameSuffix']) ? (string)$this->dsn['query']['tableNameSuffix'] : '';
        return 'tx_sealai_data' . $suffix;
    }

    public function getDimensions(): int
    {
        return isset($this->dsn['query']['dimensions']) ? (int)$this->dsn['query']['dimensions'] : 768;
    }

    protected function getApiKey(): string
    {
        $apiKeyConfiguration = isset($this->dsn['query']['api_key']) ? (string)$this->dsn['query']['api_key'] : '';
        if (!empty($apiKeyConfiguration)) {
            return $apiKeyConfiguration;
        }

        return (string)getenv('GEMINI_API_KEY');
    }

}
