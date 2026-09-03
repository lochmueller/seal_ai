<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Adapter;

use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Task\SyncTask;
use Lochmueller\SealAi\Adapter\Ai\AiSchemaManager;
use Lochmueller\SealAi\AiBridge;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\StoreInterface;

class AiSchemaManagerTest extends AbstractTest
{
    public function testExistIndexAlwaysReturnsTrue(): void
    {
        $store = new TestManagedStore();
        $schemaManager = $this->createSchemaManager($store);
        $index = new Index('dummy', []);

        self::assertTrue($schemaManager->existIndex($index));
    }

    public function testCreateIndexCallsSetupAndReturnsSyncTask(): void
    {
        $store = new TestManagedStore();
        $schemaManager = $this->createSchemaManager($store);
        $index = new Index('dummy', []);

        $result = $schemaManager->createIndex($index);

        self::assertInstanceOf(SyncTask::class, $result);
        self::assertTrue($store->setupCalled);
    }

    public function testDropIndexReturnsSyncTask(): void
    {
        $store = new TestManagedStore();
        $schemaManager = $this->createSchemaManager($store);
        $index = new Index('dummy', []);

        $result = $schemaManager->dropIndex($index);

        self::assertInstanceOf(SyncTask::class, $result);
        self::assertTrue($store->dropCalled);
    }

    private function createSchemaManager(StoreInterface&ManagedStoreInterface $store): AiSchemaManager
    {
        $aiBridge = $this->createStub(AiBridge::class);
        $aiBridge->method('getStore')->willReturn($store);

        return new AiSchemaManager($aiBridge);
    }
}

/**
 * @internal Test double covering the whole StoreInterface and ManagedStoreInterface surface
 */
class TestManagedStore implements StoreInterface, ManagedStoreInterface
{
    public bool $setupCalled = false;
    public bool $dropCalled = false;

    public function add(VectorDocument|array $documents): void {}

    public function remove(string|array $ids, array $options = []): void {}

    public function clear(array $options = []): void {}

    public function query(QueryInterface $query, array $options = []): iterable
    {
        return [];
    }

    public function supports(string $queryClass): bool
    {
        return false;
    }

    public function setup(array $options = []): void
    {
        $this->setupCalled = true;
    }

    public function drop(array $options = []): void
    {
        $this->dropCalled = true;
    }
}
