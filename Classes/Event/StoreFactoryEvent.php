<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Event;

use Lochmueller\Seal\Dto\DsnDto;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;

final class StoreFactoryEvent
{
    private (StoreInterface&ManagedStoreInterface)|null $store = null;

    public function __construct(
        private readonly DsnDto $dsn,
    ) {}

    public function getDsn(): DsnDto
    {
        return $this->dsn;
    }

    public function getStore(): (StoreInterface&ManagedStoreInterface)|null
    {
        return $this->store;
    }

    public function setStore(StoreInterface&ManagedStoreInterface $store): void
    {
        $this->store = $store;
    }
}
