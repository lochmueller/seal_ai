<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Store;

use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\Document\VectorDocumentInterface;

/**
 * Some store bridges (e.g. Azure AI Search, Supabase) can not create their index/table,
 * so the schema has to be created outside of TYPO3. Setup is a no-op and drop only
 * removes the documents, so the remote schema stays intact.
 */
readonly class UnmanagedStoreDecorator implements StoreInterface, ManagedStoreInterface
{
    public function __construct(
        private StoreInterface $store,
    ) {}

    public function setup(array $options = []): void {}

    public function drop(array $options = []): void
    {
        $this->store->clear();
    }

    public function add(VectorDocumentInterface|array $documents): void
    {
        $this->store->add($documents);
    }

    public function remove(string|array $ids, array $options = []): void
    {
        $this->store->remove($ids, $options);
    }

    public function clear(array $options = []): void
    {
        $this->store->clear($options);
    }

    public function query(QueryInterface $query, array $options = []): iterable
    {
        return $this->store->query($query, $options);
    }

    public function supports(string $queryClass): bool
    {
        return $this->store->supports($queryClass);
    }

    public function count(): int
    {
        return $this->store->count();
    }
}
