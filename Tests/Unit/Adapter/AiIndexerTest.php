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
        $store = $this->getStore();
        $vectorizer = $this->getVectorizer();

        $aiBridge = $this->createStub(AiBridge::class);
        $aiBridge->method('getStore')->willReturn($store);
        $aiBridge->method('getVectorizer')->willReturn($vectorizer);

        $indexer = new AiIndexer($aiBridge);

        $index = new Index('dummy', []);

        $result = $indexer->save($index, [
            'id' => 'dummy',
            'title' => 'Ich bin der Titel',
            'content' => 'I am the content',
        ]);

        self::assertInstanceOf(SyncTask::class, $result);
    }

}
