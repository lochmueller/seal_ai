<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Adapter\Ai;

use CmsIg\Seal\Adapter\IndexerInterface;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Task\SyncTask;
use CmsIg\Seal\Task\TaskInterface;
use Lochmueller\SealAi\AiBridge;
use Lochmueller\SealAi\Document\Transformer\TextSplitTransformer;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\TextTrimTransformer;
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;

class AiIndexer implements IndexerInterface
{
    public const int MAX_ITEMS_SPLITTER = 10;

    public function __construct(protected AiBridge $aiBridge) {}

    public function save(Index $index, array $document, array $options = []): ?TaskInterface
    {
        $this->delete($index, $document['id']);

        $transformers = [
            new TextTrimTransformer(),
            new TextSplitTransformer(
                maxItems: self::MAX_ITEMS_SPLITTER,
                chunkSize: 15000,
                overlap: 10000
            ),
        ];

        $processor = new DocumentProcessor(
            $this->aiBridge->getCachedVectorizer(),
            $this->aiBridge->getStore(),
            transformers: $transformers
        );

        $aiIndexer = new DocumentIndexer($processor);
        $aiIndexer->index([new TextDocument(
            id: $document['id'],
            content: $document['title'] . ' ' . $document['content'],
            metadata: new Metadata($document),
        )]);

        return new SyncTask($document);
    }

    public function delete(Index $index, string $identifier, array $options = []): ?TaskInterface
    {
        $removeIdentifiers = [$identifier];

        // Remove splitter items by TextSplitTransformer
        for ($i = 0; $i < self::MAX_ITEMS_SPLITTER; $i++) {
            $removeIdentifiers[] = $identifier . '-' . $i;
        }

        $this->aiBridge->getStore()->remove($removeIdentifiers);

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

    /**
     * @param string $identifier
     * @return void
     */
    public function getRemove(string $identifier): void
    {
        $this->aiBridge->getStore()->remove([$identifier]);
    }
}
