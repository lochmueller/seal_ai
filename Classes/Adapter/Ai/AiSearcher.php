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

class AiSearcher implements SearcherInterface
{
    public function __construct(protected AiBridge $aiBridge) {}

    public function search(Search $search): Result
    {
        $searchTerm = $this->recursiveFindSearchTerm($search->filters);

        if ($searchTerm === '') {
            return new Result((function () {
                yield from [];
            })(), 0, []);
        }

        $documents = [
            new TextDocument(
                id: 'search-query',
                content: $searchTerm,
            ),
        ];

        // @todo add Limiter && Cache on search term verctorizer

        $vectorDocuments = $this->aiBridge->getVectorizer()->vectorize($documents);

        $vectorDocument = $vectorDocuments[0];
        $resultItems = $this->aiBridge->getStore()->query(new VectorQuery($vectorDocument->getVector()), [
            'limit' => 200,
        ]);

        $start = $search->offset ?? 0;
        $stop = $search->limit ?? 10;

        $items = [];
        $count = 0;
        foreach ($resultItems as $i => $item) {
            $count++;
            if ($i >= $start && $i < $stop) {
                /** @var VectorDocument $item */
                $items[] = array_merge($item->getMetadata()->getArrayCopy(), ['score' => $item->getScore()]);
            }
        }

        return new Result((function () use ($items) {
            yield from $items;
        })(), $count, []);
    }

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
