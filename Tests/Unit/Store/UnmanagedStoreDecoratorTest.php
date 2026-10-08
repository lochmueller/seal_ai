<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Store;

use Lochmueller\SealAi\Store\UnmanagedStoreDecorator;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Query\VectorQuery;

class UnmanagedStoreDecoratorTest extends AbstractTest
{
    public function testDelegatesDocumentOperationsToInnerStore(): void
    {
        $innerStore = $this->getStore();
        $store = new UnmanagedStoreDecorator($innerStore);

        $store->add([
            new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3])),
            new VectorDocument('doc-2', new Vector([0.3, 0.2, 0.1])),
        ]);
        self::assertCount(2, $store);
        self::assertCount(2, $innerStore);
        self::assertTrue($store->supports(VectorQuery::class));
        self::assertCount(2, iterator_to_array($store->query(new VectorQuery(new Vector([0.1, 0.2, 0.3])))));

        $store->remove('doc-1');
        self::assertCount(1, $innerStore);
    }

    public function testSetupKeepsDocumentsAndDropOnlyClearsDocuments(): void
    {
        $innerStore = $this->getStore();
        $store = new UnmanagedStoreDecorator($innerStore);
        $store->add(new VectorDocument('doc-1', new Vector([0.1, 0.2, 0.3])));

        $store->setup();
        self::assertCount(1, $innerStore);

        $store->drop();
        self::assertCount(0, $innerStore);
    }
}
