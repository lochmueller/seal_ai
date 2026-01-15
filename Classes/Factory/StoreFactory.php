<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Factory;

use Lochmueller\SealAi\Dto\DsnDto;
use Symfony\AI\Store\Bridge\MariaDb\Store;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class StoreFactory
{
    // @todo build up store based on DSN
    // @todo used memory store for tests
    public function fromDsn(DsnDto $dsn): StoreInterface&ManagedStoreInterface
    {

        $typo3Connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');
        $store = Store::fromDbal(
            connection: $typo3Connection,
            tableName: $this->getTableName(),
        );
        $store->setup(['dimensions' => $this->getDimensions()]);

        return $store;

    }

    public function getTableName(): string
    {
        $suffix = isset($this->dsn['query']['tableNameSuffix']) ? (string) $this->dsn['query']['tableNameSuffix'] : '';
        return 'tx_sealai_data' . $suffix;
    }

    public function getDimensions(): int
    {
        return isset($this->dsn['query']['dimensions']) ? (int) $this->dsn['query']['dimensions'] : 768;
    }

}
