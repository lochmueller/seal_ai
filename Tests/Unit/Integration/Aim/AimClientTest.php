<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Integration\Aim;

use B13\Aim\Ai;
use B13\Aim\Capability\ConversationCapableInterface;
use B13\Aim\Capability\EmbeddingCapableInterface;
use B13\Aim\Domain\Model\AiProviderManifest;
use B13\Aim\Domain\Model\ProviderConfiguration;
use B13\Aim\Domain\Repository\ProviderConfigurationRepository;
use B13\Aim\Domain\Repository\RequestLogRepository;
use B13\Aim\Governance\ConfigurationAccess;
use B13\Aim\Middleware\AiMiddlewareHandler;
use B13\Aim\Middleware\AiMiddlewareInterface;
use B13\Aim\Middleware\AiMiddlewarePipeline;
use B13\Aim\Provider\AiProviderInterface;
use B13\Aim\Provider\ProviderResolver;
use B13\Aim\Registry\AiProviderRegistry;
use B13\Aim\Registry\DisabledModelRegistry;
use B13\Aim\Request\AiRequestInterface;
use B13\Aim\Request\ConversationRequest;
use B13\Aim\Request\EmbeddingRequest;
use B13\Aim\Response\EmbeddingResponse;
use B13\Aim\Response\TextResponse;
use Lochmueller\SealAi\Integration\Aim\AimClient;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

class AimClientTest extends AbstractTest
{
    private ?AiRequestInterface $lastRequest = null;

    public function testEmbedReturnsEmbeddings(): void
    {
        $client = new AimClient($this->createAi(new EmbeddingResponse([[0.1, 0.2], [0.3, 0.4]])));

        self::assertSame([[0.1, 0.2], [0.3, 0.4]], $client->embed(['first', 'second'], '', 512));
        self::assertInstanceOf(EmbeddingRequest::class, $this->lastRequest);
        self::assertSame(['first', 'second'], $this->lastRequest->input);
        self::assertSame(512, $this->lastRequest->dimensions);
        self::assertSame(AimClient::EXTENSION_KEY, $this->lastRequest->metadata['extension']);
    }

    public function testEmbedThrowsOnFailedResponse(): void
    {
        $client = new AimClient($this->createAi(new EmbeddingResponse([], errors: ['quota exceeded'])));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1759312001);
        $this->expectExceptionMessage('quota exceeded');

        $client->embed(['text']);
    }

    public function testEmbedThrowsOnNonEmbeddingResponse(): void
    {
        $client = new AimClient($this->createAi(new TextResponse('denied', errors: ['budget'])));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1759312001);

        $client->embed(['text']);
    }

    public function testEmbedThrowsOnEmbeddingCountMismatch(): void
    {
        $client = new AimClient($this->createAi(new EmbeddingResponse([[0.1]])));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1759312003);

        $client->embed(['first', 'second']);
    }

    public function testCompleteReturnsContent(): void
    {
        $client = new AimClient($this->createAi(new TextResponse('summary')));

        self::assertSame('summary', $client->complete('system', 'user'));
        self::assertInstanceOf(ConversationRequest::class, $this->lastRequest);
        self::assertSame('system', $this->lastRequest->systemPrompt);
        self::assertCount(1, $this->lastRequest->messages);
        self::assertSame('user', $this->lastRequest->messages[0]->content);
        self::assertSame(AimClient::EXTENSION_KEY, $this->lastRequest->metadata['extension']);
    }

    public function testCompleteThrowsOnErrors(): void
    {
        $client = new AimClient($this->createAi(new TextResponse('', errors: ['rate limit'])));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1759312004);
        $this->expectExceptionMessage('rate limit');

        $client->complete('system', 'user');
    }

    /**
     * AiM's proxy is final, so it is wired with a fake provider and a middleware returning the given response.
     */
    private function createAi(TextResponse $response): Ai
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($this->createStub(AiProviderInterface::class));

        $registry = new AiProviderRegistry();
        $registry->addProvider(new AiProviderManifest(
            identifier: 'fake',
            name: 'Fake',
            description: '',
            iconIdentifier: '',
            supportedModels: [],
            capabilities: [EmbeddingCapableInterface::class, ConversationCapableInterface::class],
            serviceName: 'fake',
            container: $container,
        ));

        $configurationRepository = $this->createStub(ProviderConfigurationRepository::class);
        $configurationRepository->method('findAll')->willReturn([
            new ProviderConfiguration(['uid' => 1, 'ai_provider' => 'fake', 'model' => 'fake-model', 'default' => 1, 'rerouting_allowed' => 0]),
        ]);

        $resolver = new ProviderResolver(
            $registry,
            $configurationRepository,
            $this->createStub(DisabledModelRegistry::class),
            $this->createStub(RequestLogRepository::class),
            new ConfigurationAccess(),
        );

        $onRequest = function (AiRequestInterface $request): void {
            $this->lastRequest = $request;
        };

        $middleware = new class ($response, $onRequest) implements AiMiddlewareInterface {
            public function __construct(
                private readonly TextResponse $response,
                private readonly \Closure $onRequest,
            ) {}

            public function process(
                AiRequestInterface $request,
                AiProviderInterface $provider,
                ProviderConfiguration $configuration,
                AiMiddlewareHandler $next,
            ): TextResponse {
                ($this->onRequest)($request);

                return $this->response;
            }
        };

        return new Ai($resolver, new AiMiddlewarePipeline([$middleware]), new NullLogger());
    }
}
