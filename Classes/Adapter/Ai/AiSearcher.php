<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Adapter\Ai;

use CmsIg\Seal\Adapter\SearcherInterface;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Search\Result;
use CmsIg\Seal\Search\Search;
use CmsIg\Seal\Search\Condition;
use Lochmueller\SealAi\AiBridge;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\Uid\Uuid;

class AiSearcher implements SearcherInterface
{
    protected string $searchTerm = '';

    public function __construct(protected AiBridge $aiBridge) {}

    public function search(Search $search): Result
    {
        $this->recursiveFindSearchTerm($search->filters);

        if ($this->searchTerm === '') {
            return new Result((function () {
                yield from [];
            })(), 0, []);
        }

        $documents = [
            new TextDocument(
                id: Uuid::v4()->toString(),
                content: $this->searchTerm,
            ),
        ];

        $vectorDocuments = $this->aiBridge->getVectorizer()->vectorize($documents);

        $vectorDocument = $vectorDocuments[0];
        $result = $this->aiBridge->getStore()->query(new VectorQuery($vectorDocument->getVector()), [
            'limit' => $search->limit ?? 10,
        ]);

        return new Result((function () use ($result) {
            foreach ($result as $item) {
                /** @var $item VectorDocument */
                yield array_merge($item->getMetadata()->getArrayCopy(), ['score' => $item->getScore()]);
            }
        })(), count($result), []);
    }

    private function recursiveFindSearchTerm(array $conditions): void
    {
        foreach ($conditions as $filter) {
            if ($filter instanceof Condition\SearchCondition) {
                $this->searchTerm = trim($filter->query);
            } elseif ($filter instanceof Condition\AndCondition | $filter instanceof Condition\OrCondition) {
                $this->recursiveFindSearchTerm($filter->conditions);
            };
        }

    }

    public function count(Index $index): int
    {
        // There is no general count of store documents in symfony/ai
        return 0;
    }
}
