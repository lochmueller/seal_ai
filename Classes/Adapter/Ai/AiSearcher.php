<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Adapter\Ai;

use CmsIg\Seal\Adapter\SearcherInterface;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Search\Result;
use CmsIg\Seal\Search\Search;
use CmsIg\Seal\Search\Condition;
use Lochmueller\SealAi\AiBridge;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Query\VectorQuery;

class AiSearcher implements SearcherInterface
{
    public function __construct(protected AiBridge $aiBridge) {}

    public function search(Search $search): Result
    {
        $searchTerm = $this->recursiveFindSearchTerm($search->filters);

        if ($searchTerm === '') {
            return $this->emptyResult();
        }

        $documents = [
            new TextDocument(
                id: 'search-query',
                content: $searchTerm,
            ),
        ];

        // @todo add Limiter && Cache on search term verctorizer

        $vectorDocuments = $this->aiBridge->getVectorizer()->vectorize($documents);

        $vector = $vectorDocuments[0]->getVector();
        if (!$vector instanceof Vector) {
            // The platform did not return an embedding for the search term (NullVector).
            return $this->emptyResult();
        }

        $resultItems = $this->aiBridge->getStore()->query(new VectorQuery($vector), [
            'limit' => 200,
        ]);

        $offset = $search->offset;
        $limit = $search->limit ?? 10;

        $items = [];
        $count = 0;
        foreach ($resultItems as $item) {
            if ($count >= $offset && $count < $offset + $limit) {
                $items[] = array_merge($item->getMetadata()->getArrayCopy(), ['score' => $item->getScore()]);
            }
            $count++;
        }

        return new Result((function () use ($items) {
            yield from $items;
        })(), $count, []);
    }

    private function emptyResult(): Result
    {
        return new Result((function () {
            yield from [];
        })(), 0, []);
    }

    /**
     * @param object[] $conditions
     */
    private function recursiveFindSearchTerm(array $conditions): string
    {
        foreach ($conditions as $filter) {
            if ($filter instanceof Condition\SearchCondition) {
                return trim($filter->query);
            }
            if ($filter instanceof Condition\AndCondition || $filter instanceof Condition\OrCondition) {
                $result = $this->recursiveFindSearchTerm($filter->conditions);
                if ($result !== '') {
                    return $result;
                }
            }
        }

        return '';
    }

    public function count(Index $index): int
    {
        // @todo https://github.com/symfony/ai/issues/1750
        #return count($this->aiBridge->getStore());

        // There is no general count of store documents in symfony/ai
        return 0;
    }
}
