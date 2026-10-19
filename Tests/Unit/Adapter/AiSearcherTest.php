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
use Lochmueller\SealAi\Event\SealAiSearchEvent;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Symfony\AI\Store\Document\TextDocument;
use Psr\EventDispatcher\EventDispatcherInterface;

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

        $searcher = new AiSearcher($aiBridge, $this->createStub(EventDispatcherInterface::class));
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

        $searcher = new AiSearcher($aiBridge, $this->createStub(EventDispatcherInterface::class));
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

        $searcher = new AiSearcher($aiBridge, $this->createStub(EventDispatcherInterface::class));
        $search = new Search($index, filters: [new SearchCondition('document')]);

        $result = $searcher->search($search);

        self::assertSame(2, $result->total());
    }

    public function testSearchDispatchesEventWithSearchAndDocuments(): void
    {
        $aiBridge = $this->createBridgeWithStore();
        $index = new Index('dummy', []);
        $search = new Search($index, filters: [new SearchCondition('  Term  ')]);

        $dispatched = null;
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->willReturnCallback(static function (object $event) use (&$dispatched): object {
            $dispatched = $event;
            return $event;
        });

        (new AiSearcher($aiBridge, $eventDispatcher))->search($search);

        self::assertInstanceOf(SealAiSearchEvent::class, $dispatched);
        self::assertSame($search, $dispatched->getSearch());
        self::assertCount(1, $dispatched->getDocuments());
        self::assertSame('Term', $dispatched->getDocuments()[0]->getContent());
    }

    public function testCancelledEventSkipsVectorizer(): void
    {
        $aiBridge = $this->createMock(AiBridge::class);
        $aiBridge->expects(self::never())->method('getCachedVectorizer');
        $aiBridge->expects(self::never())->method('getStore');

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function (SealAiSearchEvent $event): object {
            $event->cancel();
            return $event;
        });

        $search = new Search(new Index('dummy', []), filters: [new SearchCondition('Test')]);
        $result = (new AiSearcher($aiBridge, $eventDispatcher))->search($search);

        self::assertSame(0, $result->total());
    }

    public function testEventResultIsReturned(): void
    {
        $result = new Result((static function () {
            yield from [['id' => 'custom']];
        })(), 1, []);

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function (SealAiSearchEvent $event) use ($result): object {
            $event->setResult($result);
            return $event;
        });

        $search = new Search(new Index('dummy', []), filters: [new SearchCondition('Test')]);

        self::assertSame($result, (new AiSearcher($this->createBridgeWithStore(), $eventDispatcher))->search($search));
    }

    public function testEventCanModifyDocuments(): void
    {
        $aiBridge = $this->createBridgeWithStore();
        $index = new Index('dummy', []);
        (new AiIndexer($aiBridge))->save($index, ['id' => 'doc-1', 'title' => 'Title', 'content' => 'Content']);

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function (SealAiSearchEvent $event): object {
            $event->setDocuments([new TextDocument(id: 'search-query', content: 'Rewritten')]);
            return $event;
        });
        $search = new Search($index, filters: [new SearchCondition('Test')]);

        self::assertSame(1, (new AiSearcher($aiBridge, $eventDispatcher))->search($search)->total());

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function (SealAiSearchEvent $event): object {
            $event->setDocuments([]);
            return $event;
        });

        self::assertSame(0, (new AiSearcher($aiBridge, $eventDispatcher))->search($search)->total());
    }

    public function testCountReturnsZero(): void
    {
        $searcher = $this->createSearcher();
        $index = new Index('dummy', []);

        self::assertSame(0, $searcher->count($index));
    }

    private function createSearcher(): AiSearcher
    {
        return new AiSearcher($this->createBridgeWithStore(), $this->createStub(EventDispatcherInterface::class));
    }

    private function createBridgeWithStore(): AiBridge
    {
        $store = $this->getStore();
        $vectorizer = $this->getVectorizer();

        $aiBridge = $this->createStub(AiBridge::class);
        $aiBridge->method('getStore')->willReturn($store);
        $aiBridge->method('getVectorizer')->willReturn($vectorizer);
        $aiBridge->method('getCachedVectorizer')->willReturn($vectorizer);

        return $aiBridge;
    }
}
