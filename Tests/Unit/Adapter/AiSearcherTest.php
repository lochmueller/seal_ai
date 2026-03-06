<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Adapter;

use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Search\Condition\SearchCondition;
use CmsIg\Seal\Search\Result;
use CmsIg\Seal\Search\Search;
use Lochmueller\SealAi\Adapter\Ai\AiIndexer;
use Lochmueller\SealAi\Adapter\Ai\AiSearcher;
use Lochmueller\SealAi\AiBridge;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;

class AiSearcherTest extends AbstractTest
{
    public function testSearchWithEmptyFiltersReturnsEmptyResult(): void
    {
        $searcher = $this->createSearcher();
        $index = new Index('dummy', []);
        $search = new Search($index);

        $result = $searcher->search($search);

        self::assertInstanceOf(Result::class, $result);
        self::assertSame(0, $result->total());
    }

    public function testSearchWithBlankSearchTermReturnsEmptyResult(): void
    {
        $searcher = $this->createSearcher();
        $index = new Index('dummy', []);
        $search = new Search($index, filters: [new SearchCondition('   ')]);

        $result = $searcher->search($search);

        self::assertInstanceOf(Result::class, $result);
        self::assertSame(0, $result->total());
    }

    public function testSearchFindsIndexedDocument(): void
    {
        $aiBridge = $this->createBridgeWithStore();

        $indexer = new AiIndexer($aiBridge);
        $index = new Index('dummy', []);
        $indexer->save($index, [
            'id' => 'doc-1',
            'title' => 'Test Title',
            'content' => 'Test Content',
        ]);

        $searcher = new AiSearcher($aiBridge);
        $search = new Search($index, filters: [new SearchCondition('Test')]);

        $result = $searcher->search($search);

        self::assertInstanceOf(Result::class, $result);
        self::assertGreaterThan(0, $result->total());
    }

    public function testSearchResultContainsDocumentData(): void
    {
        $aiBridge = $this->createBridgeWithStore();

        $indexer = new AiIndexer($aiBridge);
        $index = new Index('dummy', []);
        $indexer->save($index, [
            'id' => 'doc-data',
            'title' => 'Data Title',
            'content' => 'Data Content',
        ]);

        $searcher = new AiSearcher($aiBridge);
        $search = new Search($index, filters: [new SearchCondition('Data')]);

        $result = $searcher->search($search);
        $items = iterator_to_array($result);

        self::assertNotEmpty($items);
        self::assertSame('doc-data', $items[0]['id']);
        self::assertArrayHasKey('score', $items[0]);
    }

    public function testSearchWithMultipleDocuments(): void
    {
        $aiBridge = $this->createBridgeWithStore();

        $indexer = new AiIndexer($aiBridge);
        $index = new Index('dummy', []);

        $indexer->save($index, [
            'id' => 'doc-a',
            'title' => 'Alpha',
            'content' => 'First document',
        ]);
        $indexer->save($index, [
            'id' => 'doc-b',
            'title' => 'Beta',
            'content' => 'Second document',
        ]);

        $searcher = new AiSearcher($aiBridge);
        $search = new Search($index, filters: [new SearchCondition('document')]);

        $result = $searcher->search($search);

        self::assertSame(2, $result->total());
    }

    public function testCountReturnsZero(): void
    {
        $searcher = $this->createSearcher();
        $index = new Index('dummy', []);

        self::assertSame(0, $searcher->count($index));
    }

    private function createSearcher(): AiSearcher
    {
        return new AiSearcher($this->createBridgeWithStore());
    }

    private function createBridgeWithStore(): AiBridge
    {
        $store = $this->getStore();
        $vectorizer = $this->getVectorizer();

        $aiBridge = $this->createStub(AiBridge::class);
        $aiBridge->method('getStore')->willReturn($store);
        $aiBridge->method('getVectorizer')->willReturn($vectorizer);

        return $aiBridge;
    }
}
