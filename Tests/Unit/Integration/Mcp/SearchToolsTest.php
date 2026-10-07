<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Integration\Mcp;

use CmsIg\Seal\Engine;
use CmsIg\Seal\Schema\Schema;
use Lochmueller\Seal\Schema\SchemaBuilder;
use Lochmueller\Seal\Seal;
use Lochmueller\SealAi\Adapter\Ai\AiAdapter;
use Lochmueller\SealAi\Adapter\Ai\AiIndexer;
use Lochmueller\SealAi\Adapter\Ai\AiSchemaManager;
use Lochmueller\SealAi\Adapter\Ai\AiSearcher;
use Lochmueller\SealAi\AiBridge;
use Lochmueller\SealAi\Integration\Mcp\SearchTools;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Mcp\Exception\ToolCallException;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Site\Entity\Site;

class SearchToolsTest extends AbstractTest
{
    protected bool $resetSingletonInstances = true;

    private AiBridge $aiBridge;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $store = $this->getStore();
        $vectorizer = $this->getVectorizer();
        $aiBridge = $this->createStub(AiBridge::class);
        $aiBridge->method('getStore')->willReturn($store);
        $aiBridge->method('getVectorizer')->willReturn($vectorizer);
        $this->aiBridge = $aiBridge;

        $this->site = new Site('main', 1, [
            'base' => 'https://example.com/',
            'languages' => [
                ['languageId' => 0, 'base' => '/', 'locale' => 'en_US.UTF-8', 'title' => 'English'],
            ],
        ]);
    }

    public function testSearchReturnsItemsOfTheSite(): void
    {
        $this->index(['id' => '1', 'site' => 'main', 'language' => '0', 'title' => 'Home', 'content' => 'Welcome', 'uri' => 'https://example.com/']);
        $this->index(['id' => '2', 'site' => 'other', 'language' => '0', 'title' => 'Other', 'content' => 'Other site']);
        $this->index(['id' => '3', 'site' => 'main', 'language' => '1', 'title' => 'Startseite', 'content' => 'Willkommen']);

        $result = $this->createSearchTools()->search($this->site, $this->site->getDefaultLanguage(), 'welcome');

        self::assertSame('welcome', $result['query']);
        self::assertCount(1, $result['items']);
        self::assertSame('1', $result['items'][0]['id']);
        self::assertSame('Home', $result['items'][0]['title']);
        self::assertSame('https://example.com/', $result['items'][0]['uri']);
        self::assertSame('Welcome', $result['items'][0]['snippet']);
        self::assertArrayHasKey('score', $result['items'][0]);
    }

    public function testSearchTruncatesSnippet(): void
    {
        $this->index(['id' => '1', 'site' => 'main', 'language' => '0', 'title' => 'Long', 'content' => str_repeat('Lorem ipsum ', 100)]);

        $result = $this->createSearchTools()->search($this->site, $this->site->getDefaultLanguage(), 'lorem');

        self::assertLessThanOrEqual(301, mb_strlen($result['items'][0]['snippet']));
        self::assertStringEndsWith('…', $result['items'][0]['snippet']);
    }

    public function testSearchClampsLimit(): void
    {
        $result = $this->createSearchTools()->search($this->site, $this->site->getDefaultLanguage(), 'test', 1000, -5);

        self::assertSame(SearchTools::MAX_LIMIT, $result['limit']);
        self::assertSame(0, $result['offset']);
    }

    public function testSearchWithEmptyQueryThrowsException(): void
    {
        $this->expectException(ToolCallException::class);

        $this->createSearchTools()->search($this->site, $this->site->getDefaultLanguage(), '  ');
    }

    public function testRetrieveReturnsDocumentFromPreviousSearch(): void
    {
        $this->index(['id' => '1', 'site' => 'main', 'language' => '0', 'title' => 'Home', 'content' => 'Welcome', 'uri' => 'https://example.com/']);
        $searchTools = $this->createSearchTools();
        $searchTools->search($this->site, $this->site->getDefaultLanguage(), 'welcome');

        $document = $searchTools->retrieve($this->site, $this->site->getDefaultLanguage(), '1');

        self::assertSame('1', $document['id']);
        self::assertSame('Welcome', $document['content']);
        self::assertArrayNotHasKey('site', $document);
        self::assertArrayNotHasKey('score', $document);
    }

    public function testRetrieveUnknownDocumentThrowsException(): void
    {
        $this->expectException(ToolCallException::class);

        $this->createSearchTools()->retrieve($this->site, $this->site->getDefaultLanguage(), 'unknown');
    }

    public function testRetrieveWithEmptyIdThrowsException(): void
    {
        $this->expectException(ToolCallException::class);

        $this->createSearchTools()->retrieve($this->site, $this->site->getDefaultLanguage(), ' ');
    }

    /**
     * @param array<string, mixed> $document
     */
    private function index(array $document): void
    {
        (new AiIndexer($this->aiBridge))->save((new SchemaBuilder($this->createStub(EventDispatcherInterface::class)))->getPageIndex(), $document);
    }

    private function createSearchTools(): SearchTools
    {
        $index = (new SchemaBuilder($this->createStub(EventDispatcherInterface::class)))->getPageIndex();
        $engine = new Engine(
            new AiAdapter(new AiSchemaManager($this->aiBridge), new AiIndexer($this->aiBridge), new AiSearcher($this->aiBridge)),
            new Schema([SchemaBuilder::DEFAULT_INDEX => $index]),
        );

        $seal = $this->createStub(Seal::class);
        $seal->method('buildEngineBySite')->willReturn($engine);
        $seal->method('getIndexNameBySite')->willReturn(SchemaBuilder::DEFAULT_INDEX);

        $cache = new VariableFrontend(SearchTools::CACHE_IDENTIFIER, new TransientMemoryBackend());
        $cacheManager = $this->createStub(CacheManager::class);
        $cacheManager->method('getCache')->willReturn($cache);

        return new SearchTools($seal, $cacheManager);
    }
}
