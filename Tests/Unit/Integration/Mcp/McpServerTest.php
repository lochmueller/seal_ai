<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Integration\Mcp;

use Lochmueller\SealAi\Integration\Mcp\McpServer;
use Lochmueller\SealAi\Integration\Mcp\SearchTools;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Site\Entity\Site;

class McpServerTest extends AbstractTest
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->site = new Site('main', 1, [
            'base' => 'https://example.com/',
            'languages' => [
                ['languageId' => 0, 'base' => '/', 'locale' => 'en_US.UTF-8', 'title' => 'English'],
            ],
        ]);
    }

    public function testListsReadOnlyTools(): void
    {
        $mcpServer = new McpServer($this->createStub(SearchTools::class), new ResponseFactory(), new StreamFactory(), new NullLogger());

        $sessionId = $this->initialize($mcpServer);
        $response = $this->post($mcpServer, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $sessionId);
        $data = $this->decode($response);

        $tools = array_column($data['result']['tools'], null, 'name');
        self::assertSame(['search', 'retrieve'], array_keys($tools));
        self::assertTrue($tools['search']['annotations']['readOnlyHint']);
        self::assertTrue($tools['retrieve']['annotations']['readOnlyHint']);
        self::assertSame(['query'], $tools['search']['inputSchema']['required']);
    }

    public function testCallsSearchToolForSite(): void
    {
        $searchTools = $this->createMock(SearchTools::class);
        $searchTools->expects(self::once())
            ->method('search')
            ->with($this->site, self::anything(), 'typo3', 5, 0)
            ->willReturn(['query' => 'typo3', 'total' => 0, 'offset' => 0, 'limit' => 5, 'items' => []]);
        $mcpServer = new McpServer($searchTools, new ResponseFactory(), new StreamFactory(), new NullLogger());

        $sessionId = $this->initialize($mcpServer);
        $response = $this->post($mcpServer, [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'search', 'arguments' => ['query' => 'typo3', 'limit' => 5]],
        ], $sessionId);
        $data = $this->decode($response);

        self::assertFalse($data['result']['isError'] ?? false);
        self::assertSame('typo3', $data['result']['structuredContent']['query']);
    }

    private function initialize(McpServer $mcpServer): string
    {
        $response = $this->post($mcpServer, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0.0'],
            ],
        ]);
        self::assertSame(200, $response->getStatusCode());
        $sessionId = $response->getHeaderLine('Mcp-Session-Id');
        self::assertNotSame('', $sessionId);

        $this->post($mcpServer, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $sessionId);

        return $sessionId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(McpServer $mcpServer, array $payload, ?string $sessionId = null): ResponseInterface
    {
        $headers = [
            'Host' => 'example.com',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream',
        ];
        if ($sessionId !== null) {
            $headers['Mcp-Session-Id'] = $sessionId;
            $headers['Mcp-Protocol-Version'] = '2025-06-18';
        }
        $body = (new StreamFactory())->createStream((string) json_encode($payload));
        $request = (new ServerRequest('https://example.com/seal/mcp', 'POST', $body, $headers))
            ->withAttribute('site', $this->site)
            ->withAttribute('language', $this->site->getDefaultLanguage());

        return $mcpServer->handle($request, $this->site);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        // Server-sent events: use the data line
        if (preg_match('/^data: (.+)$/m', $body, $matches)) {
            $body = $matches[1];
        }
        $data = json_decode($body, true);
        self::assertIsArray($data, 'Invalid response: ' . $body);

        return $data;
    }
}
