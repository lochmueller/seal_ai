<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Adapter\Ai;

use CmsIg\Seal\Adapter\IndexerInterface;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Task\SyncTask;
use CmsIg\Seal\Task\TaskInterface;
use Lochmueller\SealAi\AiBridge;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Indexer;
use Symfony\Component\Uid\Uuid;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AiIndexer implements IndexerInterface
{
    public function __construct(protected AiBridge $aiBridge) {}

    public function save(Index $index, array $document, array $options = []): ?TaskInterface
    {
        $this->delete($index, $document['id']);

        $memory = new InMemoryLoader([new TextDocument(
            id: Uuid::v4(),
            content: $document['title'] . ' ' . $document['content'],
            metadata: new Metadata($document),
        )]);

        $aiIndexer = new Indexer($memory, $this->aiBridge->getVectorizer(), $this->aiBridge->getStore(), 'memory');
        $aiIndexer->index();

        return new SyncTask(null);
    }

    public function delete(Index $index, string $identifier, array $options = []): ?TaskInterface
    {
        // @todo check
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->aiBridge->getTableName());
        $statement = $connection->prepare('DELETE FROM ' . $this->aiBridge->getTableName() . ' WHERE JSON_EXTRACT(metadata, "$.id") = :identifier');
        $statement->bindValue('identifier', $identifier);
        $statement->executeStatement();

        return new SyncTask(null);
    }

    public function bulk(Index $index, iterable $saveDocuments, iterable $deleteDocumentIdentifiers, int $bulkSize = 100, array $options = []): ?TaskInterface
    {
        foreach ($deleteDocumentIdentifiers as $deleteDocumentIdentifier) {
            $this->delete($index, $deleteDocumentIdentifier);
        }
        foreach ($saveDocuments as $saveDocument) {
            $this->save($index, $saveDocument);
        }
        return new SyncTask(null);
    }
}
