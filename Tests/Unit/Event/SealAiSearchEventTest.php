<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Event;

use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Search\Search;
use Lochmueller\SealAi\Event\SealAiSearchEvent;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Symfony\AI\Store\Document\TextDocument;

class SealAiSearchEventTest extends AbstractTest
{
    public function testEventHasNoResultByDefault(): void
    {
        $search = new Search(new Index('dummy', []));
        $documents = [new TextDocument(id: 'search-query', content: 'Term')];
        $event = new SealAiSearchEvent($search, $documents);

        self::assertSame($search, $event->getSearch());
        self::assertSame($documents, $event->getDocuments());
        self::assertNull($event->getResult());
    }

    public function testCancelSetsEmptyResult(): void
    {
        $event = new SealAiSearchEvent(new Search(new Index('dummy', [])), []);

        $event->cancel();

        self::assertNotNull($event->getResult());
        self::assertSame(0, $event->getResult()->total());
        self::assertSame([], iterator_to_array($event->getResult()));
    }
}
