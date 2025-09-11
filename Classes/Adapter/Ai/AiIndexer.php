<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Adapter\Ai;

use CmsIg\Seal\Adapter\IndexerInterface;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Task\SyncTask;
use CmsIg\Seal\Task\TaskInterface;
use Lochmueller\SealAi\AiBridge;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\Component\Uid\Uuid;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AiIndexer implements IndexerInterface
{
    public function __construct(protected AiBridge $aiBridge)
    {
    }

    public function save(Index $index, array $document, array $options = []): TaskInterface|null
    {
        $this->delete($index, $document['id']);
        $this->aiBridge->getIndexer()->withSource([new TextDocument(
            id: Uuid::v4(),
            content: $document['title'] . ' ' . $document['content'],
            metadata: new Metadata($document),
        )])->index();

        return new SyncTask(null);
    }

    public function delete(Index $index, string $identifier, array $options = []): TaskInterface|null
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->aiBridge->getTableName());
        $statement = $connection->prepare('DELETE FROM '.$this->aiBridge->getTableName().' WHERE JSON_EXTRACT(metadata, "$.id") = :identifier');
        $statement->bindValue('identifier', $identifier);

        return new SyncTask(null);
    }

    public function bulk(Index $index, iterable $saveDocuments, iterable $deleteDocumentIdentifiers, int $bulkSize = 100, array $options = []): TaskInterface|null
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
