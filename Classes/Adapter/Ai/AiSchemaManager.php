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
    public function __construct(protected AiBridge $aiBridge)
    {
    }

    public function existIndex(Index $index): bool
    {
        // Always exists
        return true;
    }

    public function dropIndex(Index $index, array $options = []): TaskInterface|null
    {
        $this->aiBridge->getStore()->drop();

        return new SyncTask(null);
    }

    public function createIndex(Index $index, array $options = []): TaskInterface|null
    {
        // Always exists
        return new SyncTask(null);
    }
}
