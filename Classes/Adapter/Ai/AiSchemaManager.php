<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Adapter\Ai;

use CmsIg\Seal\Adapter\SchemaManagerInterface;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Task\SyncTask;
use CmsIg\Seal\Task\TaskInterface;
use Lochmueller\SealAi\AiBridge;

class AiSchemaManager implements SchemaManagerInterface
{
    public function __construct(protected AiBridge $aiBridge) {}

    public function existIndex(Index $index): bool
    {
        // symfony/ai offers no way to check the existence of a store.
        // ManagedStoreInterface only knows setup() and drop(), and setup() is
        // idempotent in every bridge, so we always report the index as existing.
        return true;
    }

    public function dropIndex(Index $index, array $options = []): ?TaskInterface
    {
        $this->aiBridge->getStore()->drop();

        return new SyncTask(null);
    }

    public function createIndex(Index $index, array $options = []): ?TaskInterface
    {
        $this->aiBridge->getStore()->setup();

        return new SyncTask(null);
    }
}
