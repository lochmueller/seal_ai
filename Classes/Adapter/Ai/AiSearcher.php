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
use Symfony\Component\Uid\Uuid;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
                id: Uuid::v4(),
                content: $this->searchTerm,
            ),
        ];

        $vectorDocuments = $this->aiBridge->getVectorizer()->vectorize($documents);

        $vectorDocument = $vectorDocuments[0];
        $result = $this->aiBridge->getStore()->query($vectorDocument->vector, [
            'limit' => $search->limit ?? 10,
        ]);

        return new Result((function () use ($result) {
            foreach ($result as $item) {
                /** @var $item VectorDocument */
                yield array_merge($item->metadata->getArrayCopy(), ['score' => $item->score]);
            }
        })(), count($result), []);
    }

    private function recursiveFindSearchTerm(array $conditions): void
    {
        foreach ($conditions as $filter) {
            if ($filter instanceof Condition\SearchCondition) {
                $this->searchTerm = $filter->query;
            } elseif ($filter instanceof Condition\AndCondition | $filter instanceof Condition\OrCondition) {
                $this->recursiveFindSearchTerm($filter->conditions);
            };
        }

    }

    public function count(Index $index): int
    {
        // @todo change to store
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($this->aiBridge->getTableName());

        return (int) $queryBuilder
            ->count('*')
            ->from($this->aiBridge->getTableName())
            ->executeQuery()
            ->fetchOne();
    }
}
