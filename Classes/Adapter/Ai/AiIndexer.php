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
        // @todo Migrate to new remove function from store
        // $this->aiBridge->getStore()->remove([$identifier]);

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
