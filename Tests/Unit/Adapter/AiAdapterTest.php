<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Adapter;

use CmsIg\Seal\Adapter\IndexerInterface;
use CmsIg\Seal\Adapter\SchemaManagerInterface;
use CmsIg\Seal\Adapter\SearcherInterface;
use Lochmueller\SealAi\Adapter\Ai\AiAdapter;
use Lochmueller\SealAi\Adapter\Ai\AiIndexer;
use Lochmueller\SealAi\Adapter\Ai\AiSchemaManager;
use Lochmueller\SealAi\Adapter\Ai\AiSearcher;
use Lochmueller\SealAi\AiBridge;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;

class AiAdapterTest extends AbstractTest
{
    public function testGetSchemaManagerReturnsAiSchemaManager(): void
    {
        $adapter = $this->createAdapter();

        $result = $adapter->getSchemaManager();

        self::assertInstanceOf(SchemaManagerInterface::class, $result);
        self::assertInstanceOf(AiSchemaManager::class, $result);
    }

    public function testGetIndexerReturnsAiIndexer(): void
    {
        $adapter = $this->createAdapter();

        $result = $adapter->getIndexer();

        self::assertInstanceOf(IndexerInterface::class, $result);
        self::assertInstanceOf(AiIndexer::class, $result);
    }

    public function testGetSearcherReturnsAiSearcher(): void
    {
        $adapter = $this->createAdapter();

        $result = $adapter->getSearcher();

        self::assertInstanceOf(SearcherInterface::class, $result);
        self::assertInstanceOf(AiSearcher::class, $result);
    }

    private function createAdapter(): AiAdapter
    {
        $aiBridge = $this->createStub(AiBridge::class);
        $schemaManager = new AiSchemaManager($aiBridge);
        $indexer = new AiIndexer($aiBridge);
        $searcher = new AiSearcher($aiBridge);

        return new AiAdapter($schemaManager, $indexer, $searcher);
    }
}
