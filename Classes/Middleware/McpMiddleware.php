<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Middleware;

use Lochmueller\SealAi\Integration\Mcp\McpServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Read-only MCP endpoint ("<site base>/seal/mcp") for the search of the current site.
 * Only active, if mcp/sdk is installed and a "sealAiMcpToken" is configured in the site.
 */
class McpMiddleware implements MiddlewareInterface
{
    public const URI_PATH_IDENTIFIER = '/seal/mcp';

    public function __construct(
        private readonly ?McpServer $mcpServer = null,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->mcpServer === null || !str_ends_with(rtrim($request->getUri()->getPath(), '/'), self::URI_PATH_IDENTIFIER)) {
            return $handler->handle($request);
        }

        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return $handler->handle($request);
        }

        $token = $site->getConfiguration()['sealAiMcpToken'] ?? '';
        if (!\is_string($token) || trim($token) === '') {
            // MCP server is disabled for this site
            return $handler->handle($request);
        }

        // CORS preflight requests do not carry credentials
        if ($request->getMethod() !== 'OPTIONS' && !$this->isAuthorized($request, trim($token))) {
            return new JsonResponse(
                ['error' => 'invalid_token', 'error_description' => 'Missing or invalid bearer token.'],
                401,
                ['WWW-Authenticate' => 'Bearer realm="seal_ai_mcp", error="invalid_token"'],
            );
        }

        return $this->mcpServer->handle($request, $site);
    }

    private function isAuthorized(ServerRequestInterface $request, string $token): bool
    {
        if (!preg_match('/^Bearer\s+(\S+)$/i', $request->getHeaderLine('Authorization'), $matches)) {
            return false;
        }

        return hash_equals($token, $matches[1]);
    }
}
