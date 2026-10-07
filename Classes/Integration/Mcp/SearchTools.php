<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Integration\Mcp;

use CmsIg\Seal\Exception\DocumentNotFoundException;
use CmsIg\Seal\Search\Condition\Condition;
use Lochmueller\Seal\Seal;
use Mcp\Exception\ToolCallException;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Read-only search tools of the MCP server. All operations are bound to one site and language.
 */
class SearchTools
{
    public const CACHE_IDENTIFIER = 'seal_ai_mcp';

    public const MAX_LIMIT = 50;

    private const SNIPPET_LENGTH = 300;

    private const CACHE_LIFETIME = 86400;

    private const DOCUMENT_FIELDS = ['id', 'title', 'uri', 'content', 'tags', 'extension', 'size', 'indexdate', 'language'];

    public function __construct(
        private readonly Seal $seal,
        private readonly CacheManager $cacheManager,
    ) {}

    /**
     * @return array{query: string, total: int, offset: int, limit: int, items: list<array<string, mixed>>}
     */
    public function search(Site $site, SiteLanguage $language, string $query, int $limit = 10, int $offset = 0): array
    {
        $query = trim($query);
        if ($query === '') {
            throw new ToolCallException('The search query must not be empty.');
        }
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $offset = max(0, $offset);

        $engine = $this->seal->buildEngineBySite($site);
        $result = $engine->createSearchBuilder($this->seal->getIndexNameBySite($site))
            ->addFilter(Condition::search($query))
            ->addFilter(Condition::equal('site', $site->getIdentifier()))
            ->addFilter(Condition::equal('language', (string) $language->getLanguageId()))
            ->offset($offset)
            ->limit($limit)
            ->getResult();

        $items = [];
        foreach ($result as $document) {
            if (!$this->belongsTo($document, $site, $language)) {
                continue;
            }
            $this->getCache()->set($this->getCacheIdentifier($site, $language, (string) $document['id']), $document, [], self::CACHE_LIFETIME);

            $item = [
                'id' => (string) $document['id'],
                'title' => (string) ($document['title'] ?? ''),
                'uri' => (string) ($document['uri'] ?? ''),
                'snippet' => $this->snippet((string) ($document['content'] ?? '')),
                'tags' => $document['tags'] ?? [],
            ];
            if (isset($document['score'])) {
                $item['score'] = $document['score'];
            }
            $items[] = $item;
        }

        return [
            'query' => $query,
            'total' => $result->total(),
            'offset' => $offset,
            'limit' => $limit,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieve(Site $site, SiteLanguage $language, string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new ToolCallException('The document ID must not be empty.');
        }

        // Vector stores have no lookup by ID, so documents of previous search results are cached
        $document = $this->getCache()->get($this->getCacheIdentifier($site, $language, $id));
        if (!\is_array($document)) {
            try {
                $document = $this->seal->buildEngineBySite($site)->getDocument($this->seal->getIndexNameBySite($site), $id);
            } catch (DocumentNotFoundException) {
                $document = null;
            }
        }

        if (!\is_array($document) || !$this->belongsTo($document, $site, $language)) {
            throw new ToolCallException(\sprintf('Document "%s" not found. Use the "search" tool to find documents first.', $id));
        }

        return array_intersect_key($document, array_flip(self::DOCUMENT_FIELDS));
    }

    /**
     * @param array<string, mixed> $document
     */
    private function belongsTo(array $document, Site $site, SiteLanguage $language): bool
    {
        if (!isset($document['id'])) {
            return false;
        }
        if (isset($document['site']) && $document['site'] !== $site->getIdentifier()) {
            return false;
        }

        return !isset($document['language']) || (string) $document['language'] === (string) $language->getLanguageId();
    }

    private function snippet(string $content): string
    {
        $content = trim((string) preg_replace('/\s+/u', ' ', $content));
        if (mb_strlen($content) <= self::SNIPPET_LENGTH) {
            return $content;
        }

        return rtrim(mb_substr($content, 0, self::SNIPPET_LENGTH)) . '…';
    }

    private function getCacheIdentifier(Site $site, SiteLanguage $language, string $id): string
    {
        return sha1($site->getIdentifier() . '|' . $language->getLanguageId() . '|' . $id);
    }

    private function getCache(): FrontendInterface
    {
        return $this->cacheManager->getCache(self::CACHE_IDENTIFIER);
    }
}
