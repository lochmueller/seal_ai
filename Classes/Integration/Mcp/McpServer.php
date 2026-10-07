<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Integration\Mcp;

use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Builds the read-only MCP server (mcp/sdk) for one site and handles a single HTTP request.
 */
class McpServer
{
    private const SESSION_TTL = 3600;

    public function __construct(
        private readonly SearchTools $searchTools,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(ServerRequestInterface $request, Site $site): ResponseInterface
    {
        $language = $request->getAttribute('language');
        if (!$language instanceof SiteLanguage) {
            $language = $site->getDefaultLanguage();
        }

        // The SEAL adapter factories resolve the site via the global request
        $GLOBALS['TYPO3_REQUEST'] = $request;

        $transport = new StreamableHttpTransport(
            $request,
            $this->responseFactory,
            $this->streamFactory,
            $this->logger,
            [
                new CorsMiddleware(),
                new DnsRebindingProtectionMiddleware([$request->getUri()->getHost(), 'localhost', '127.0.0.1', '[::1]'], $this->responseFactory, $this->streamFactory),
            ],
        );

        return $this->buildServer($site, $language)->run($transport);
    }

    public function buildServer(Site $site, SiteLanguage $language): Server
    {
        $searchTools = $this->searchTools;
        $base = (string) $language->getBase();

        return Server::builder()
            ->setServerInfo('seal_ai: ' . $site->getIdentifier(), $this->getVersion(), 'Read-only search of ' . $base)
            ->setInstructions(
                'Read-only access to the website search of ' . $base . ' (language: ' . $language->getLocale()->getName() . '). '
                . 'Use "search" to find relevant pages and documents, then "retrieve" with an ID from the search result to get the full content.',
            )
            ->setLogger($this->logger)
            ->setSession(new FileSessionStore(Environment::getVarPath() . '/seal_ai/mcp_sessions', self::SESSION_TTL))
            ->addTool(
                static fn(string $query, int $limit = 10, int $offset = 0): array => $searchTools->search($site, $language, $query, $limit, $offset),
                name: 'search',
                title: 'Search',
                description: 'Search the content of the website ' . $base . '. Returns matching pages and documents with ID, title, URI and a short snippet.',
                annotations: new ToolAnnotations(title: 'Search', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'The search query (natural language is supported for semantic search).', 'minLength' => 1],
                        'limit' => ['type' => 'integer', 'description' => 'Maximum number of results.', 'minimum' => 1, 'maximum' => SearchTools::MAX_LIMIT, 'default' => 10],
                        'offset' => ['type' => 'integer', 'description' => 'Number of results to skip (pagination).', 'minimum' => 0, 'default' => 0],
                    ],
                    'required' => ['query'],
                ],
            )
            ->addTool(
                static fn(string $id): array => $searchTools->retrieve($site, $language, $id),
                name: 'retrieve',
                title: 'Retrieve document',
                description: 'Retrieve the full content of a page or document by the ID returned from the "search" tool.',
                annotations: new ToolAnnotations(title: 'Retrieve document', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'Document ID from a search result.', 'minLength' => 1],
                    ],
                    'required' => ['id'],
                ],
            )
            ->build();
    }

    private function getVersion(): string
    {
        try {
            return ExtensionManagementUtility::getExtensionVersion('seal_ai') ?: '1.0.0';
        } catch (\Throwable) {
            return '1.0.0';
        }
    }
}
