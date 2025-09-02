<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Adapter\Ai;

use CmsIg\Seal\Adapter\AdapterInterface;
use CmsIg\Seal\Adapter\IndexerInterface;
use CmsIg\Seal\Adapter\SchemaManagerInterface;
use CmsIg\Seal\Adapter\SearcherInterface;

readonly class AiAdapter implements AdapterInterface
{
    public function __construct(
        private AiSchemaManager $schemaManager,
        private AiIndexer       $indexer,
        private AiSearcher      $searcher,
    ) {}

    public function getSchemaManager(): SchemaManagerInterface
    {
        return $this->schemaManager;
    }

    public function getIndexer(): IndexerInterface
    {
        return $this->indexer;
    }

    public function getSearcher(): SearcherInterface
    {
        return $this->searcher;
    }
}
