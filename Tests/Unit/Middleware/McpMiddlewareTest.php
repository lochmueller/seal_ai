<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Middleware;

use Lochmueller\SealAi\Integration\Mcp\McpServer;
use Lochmueller\SealAi\Middleware\McpMiddleware;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

class McpMiddlewareTest extends AbstractTest
{
    public function testPassesThroughWithoutMcpSdk(): void
    {
        $middleware = new McpMiddleware(null);

        $response = $middleware->process($this->createRequest('secret', 'secret'), $this->createHandler());

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPassesThroughForOtherPaths(): void
    {
        $mcpServer = $this->createMock(McpServer::class);
        $mcpServer->expects(self::never())->method('handle');

        $response = (new McpMiddleware($mcpServer))->process($this->createRequest('secret', 'secret', '/search'), $this->createHandler());

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPassesThroughWithoutConfiguredToken(): void
    {
        $mcpServer = $this->createMock(McpServer::class);
        $mcpServer->expects(self::never())->method('handle');

        $response = (new McpMiddleware($mcpServer))->process($this->createRequest('', 'secret'), $this->createHandler());

        self::assertSame(404, $response->getStatusCode());
    }

    public function testReturnsUnauthorizedForMissingToken(): void
    {
        $mcpServer = $this->createMock(McpServer::class);
        $mcpServer->expects(self::never())->method('handle');

        $response = (new McpMiddleware($mcpServer))->process($this->createRequest('secret', null), $this->createHandler());

        self::assertSame(401, $response->getStatusCode());
        self::assertStringStartsWith('Bearer', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function testReturnsUnauthorizedForInvalidToken(): void
    {
        $mcpServer = $this->createMock(McpServer::class);
        $mcpServer->expects(self::never())->method('handle');

        $response = (new McpMiddleware($mcpServer))->process($this->createRequest('secret', 'wrong'), $this->createHandler());

        self::assertSame(401, $response->getStatusCode());
    }

    public function testHandlesRequestWithValidToken(): void
    {
        $mcpServer = $this->createMock(McpServer::class);
        $mcpServer->expects(self::once())->method('handle')->willReturn(new Response(null, 202));

        $response = (new McpMiddleware($mcpServer))->process($this->createRequest('secret', 'secret', '/en/seal/mcp/'), $this->createHandler());

        self::assertSame(202, $response->getStatusCode());
    }

    private function createRequest(string $configuredToken, ?string $token, string $path = '/seal/mcp'): ServerRequestInterface
    {
        $site = new Site('main', 1, ['base' => 'https://example.com/', 'sealAiMcpToken' => $configuredToken]);
        $request = (new ServerRequest('https://example.com' . $path, 'POST'))->withAttribute('site', $site);

        return $token === null ? $request : $request->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function createHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(null, 404);
            }
        };
    }
}
