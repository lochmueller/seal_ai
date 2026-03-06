<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Adapter;

use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Task\SyncTask;
use Lochmueller\SealAi\Adapter\Ai\AiIndexer;
use Lochmueller\SealAi\AiBridge;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;

class AiIndexerTest extends AbstractTest
{
    public function testSaveNewDocumentsToIndex(): void
    {
        $indexer = $this->createIndexer();
        $index = new Index('dummy', []);

        $result = $indexer->save($index, [
            'id' => 'dummy',
            'title' => 'Ich bin der Titel',
            'content' => 'I am the content',
        ]);

        self::assertInstanceOf(SyncTask::class, $result);
    }

    public function testDeleteReturnsSyncTask(): void
    {
        $indexer = $this->createIndexer();
        $index = new Index('dummy', []);

        $result = $indexer->delete($index, 'non-existent-id');

        self::assertInstanceOf(SyncTask::class, $result);
    }

    public function testBulkReturnsSyncTask(): void
    {
        $indexer = $this->createIndexer();
        $index = new Index('dummy', []);

        $saveDocuments = [
            ['id' => 'bulk-1', 'title' => 'Bulk Title 1', 'content' => 'Bulk Content 1'],
            ['id' => 'bulk-2', 'title' => 'Bulk Title 2', 'content' => 'Bulk Content 2'],
        ];

        $result = $indexer->bulk($index, $saveDocuments, []);

        self::assertInstanceOf(SyncTask::class, $result);
    }

    public function testBulkDeletesAndSaves(): void
    {
        $indexer = $this->createIndexer();
        $index = new Index('dummy', []);

        $indexer->save($index, [
            'id' => 'to-delete',
            'title' => 'Delete Me',
            'content' => 'Content',
        ]);

        $saveDocuments = [
            ['id' => 'new-doc', 'title' => 'New', 'content' => 'New Content'],
        ];

        $result = $indexer->bulk($index, $saveDocuments, ['to-delete']);

        self::assertInstanceOf(SyncTask::class, $result);
    }

    public function testSaveOverwritesExistingDocument(): void
    {
        $indexer = $this->createIndexer();
        $index = new Index('dummy', []);

        $indexer->save($index, [
            'id' => 'overwrite-me',
            'title' => 'Original',
            'content' => 'Original Content',
        ]);

        $result = $indexer->save($index, [
            'id' => 'overwrite-me',
            'title' => 'Updated',
            'content' => 'Updated Content',
        ]);

        self::assertInstanceOf(SyncTask::class, $result);
    }

    private function createIndexer(): AiIndexer
    {
        $store = $this->getStore();
        $vectorizer = $this->getVectorizer();

        $aiBridge = $this->createStub(AiBridge::class);
        $aiBridge->method('getStore')->willReturn($store);
        $aiBridge->method('getVectorizer')->willReturn($vectorizer);

        return new AiIndexer($aiBridge);
    }
}
