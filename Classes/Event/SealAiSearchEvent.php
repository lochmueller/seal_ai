<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Event;

use CmsIg\Seal\Search\Result;
use CmsIg\Seal\Search\Search;
use Symfony\AI\Store\Document\TextDocument;

/**
 * Dispatched before the search term is vectorized (embedding request) and the store is queried.
 * Listeners can modify the documents (search term) or skip the AI search by providing a result (e.g. rate limiting).
 */
final class SealAiSearchEvent
{
    private ?Result $result = null;

    /**
     * @param list<TextDocument> $documents
     */
    public function __construct(
        private readonly Search $search,
        private array $documents,
    ) {}

    public function getSearch(): Search
    {
        return $this->search;
    }

    /**
     * @return list<TextDocument>
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    /**
     * @param list<TextDocument> $documents
     */
    public function setDocuments(array $documents): void
    {
        $this->documents = $documents;
    }

    public function getResult(): ?Result
    {
        return $this->result;
    }

    /**
     * Skip the AI search (no embedding request, no store query) and return this result instead.
     */
    public function setResult(Result $result): void
    {
        $this->result = $result;
    }

    /**
     * Skip the AI search and return an empty result.
     */
    public function cancel(): void
    {
        $this->setResult(new Result((static function () {
            yield from [];
        })(), 0, []));
    }
}
