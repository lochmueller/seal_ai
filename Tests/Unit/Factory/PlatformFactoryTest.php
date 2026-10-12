<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Factory;

use Lochmueller\Seal\DsnParser;
use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\SealAi\Event\CreatePlatformEvent;
use Lochmueller\SealAi\Factory\PlatformFactory;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Bridge\Failover\FailoverPlatform;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;

class PlatformFactoryTest extends AbstractTest
{
    public function testEventSchemeDispatchesEventAndReturnsPlatform(): void
    {
        $dsn = new DsnDto(scheme: 'event');
        $platform = $this->createStub(PlatformInterface::class);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn(CreatePlatformEvent $event): bool => $event->getDsn() === $dsn))
            ->willReturnCallback(static function (CreatePlatformEvent $event) use ($platform): CreatePlatformEvent {
                $event->setPlatform($platform);
                return $event;
            });

        $factory = new PlatformFactory($eventDispatcher, $this->createDsnParser());
        $result = $factory->fromDsn($dsn);

        self::assertSame($platform, $result);
    }

    public function testEventSchemeThrowsWhenNoPlatformProvided(): void
    {
        $dsn = new DsnDto(scheme: 'event');

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(static fn(CreatePlatformEvent $event): CreatePlatformEvent => $event);

        $factory = new PlatformFactory($eventDispatcher, $this->createDsnParser());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No platform provided by event listener for DSN scheme "event"');
        $factory->fromDsn($dsn);
    }

    public function testUnsupportedSchemeThrowsInvalidArgumentException(): void
    {
        $dsn = new DsnDto(scheme: 'unsupported-scheme');

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $factory = new PlatformFactory($eventDispatcher, $this->createDsnParser());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported DSN scheme: unsupported-scheme');
        $factory->fromDsn($dsn);
    }

    public function testFailoverSchemeCreatesFailoverPlatform(): void
    {
        $dsn = $this->createDsnParser()->parse(
            'failover://default?platforms[]=openai://sk-test@default&platforms[]=' . urlencode('ollama://localhost:11434?foo=bar') . '&model=text-embedding-3-small',
        );

        $factory = new PlatformFactory($this->createStub(EventDispatcherInterface::class), $this->createDsnParser());

        self::assertInstanceOf(FailoverPlatform::class, $factory->fromDsn($dsn));
    }

    public function testFailoverSchemeThrowsWithoutPlatforms(): void
    {
        $dsn = new DsnDto(scheme: 'failover', host: 'default');

        $factory = new PlatformFactory($this->createStub(EventDispatcherInterface::class), $this->createDsnParser());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1759312800);
        $factory->fromDsn($dsn);
    }

    public function testFailoverSchemePropagatesInvalidNestedDsn(): void
    {
        $dsn = new DsnDto(scheme: 'failover', query: ['platforms' => ['unsupported-scheme://default']]);

        $factory = new PlatformFactory($this->createStub(EventDispatcherInterface::class), $this->createDsnParser());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported DSN scheme: unsupported-scheme');
        $factory->fromDsn($dsn);
    }

    public function testCohereSchemeCreatesPlatform(): void
    {
        $dsn = $this->createDsnParser()->parse('cohere://api-key@default');

        $factory = new PlatformFactory($this->createStub(EventDispatcherInterface::class), $this->createDsnParser());

        self::assertInstanceOf(Platform::class, $factory->fromDsn($dsn));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function platformDsnProvider(): iterable
    {
        yield 'openai' => ['openai://sk-test@default'];
        yield 'anthropic' => ['anthropic://api-key@default'];
        yield 'gemini' => ['gemini://api-key@default'];
        yield 'openrouter' => ['openrouter://api-key@default'];
        yield 'vertex' => ['vertex://europe-west1?project_id=my-project&api_key=api-key'];
        yield 'vertexai' => ['vertexai://europe-west1?project_id=my-project&api_key=api-key'];
        yield 'bedrock' => ['bedrock://default'];
        yield 'mistral' => ['mistral://api-key@default'];
        yield 'ollama' => ['ollama://localhost:11434'];
        yield 'ollama without host' => ['ollama://'];
        yield 'huggingface' => ['huggingface://api-key@default?provider=hf-inference'];
        yield 'huggingface default provider' => ['huggingface://api-key@default'];
        yield 'replicate' => ['replicate://api-key@default'];
        yield 'lmstudio' => ['lmstudio://localhost:1234'];
        yield 'albert' => ['albert://api-key@albert.api.etalab.gouv.fr'];
        yield 'cartesia' => ['cartesia://api-key@default?version=v1'];
        yield 'elevenlabs' => ['elevenlabs://api-key@api.elevenlabs.io'];
        yield 'perplexity' => ['perplexity://pplx-test@default'];
        yield 'scaleway' => ['scaleway://api-key@default'];
        yield 'voyage' => ['voyage://api-key@default'];
        yield 'cohere' => ['cohere://api-key@default'];
        yield 'deepseek' => ['deepseek://api-key@default'];
        yield 'cerebras' => ['cerebras://csk-test@default'];
        yield 'edenai' => ['edenai://api-key@default'];
        yield 'decart' => ['decart://api-key@api.decart.ai'];
        yield 'aimlapi' => ['aimlapi://api-key@api.aimlapi.com'];
        yield 'docker' => ['docker://localhost:12434'];
        yield 'transformers' => ['transformers://default'];
        yield 'generic' => ['generic://api.example.com?api_key=api-key'];
        yield 'openresponses' => ['openresponses://api-key@api.example.com?path=/v1/responses'];
        yield 'azure-openai' => ['azure-openai://api-key@my-resource.openai.azure.com?deployment=my-deployment&api_version=2024-02-01'];
        yield 'azure-meta' => ['azure-meta://api-key@my-resource.services.ai.azure.com'];
    }

    #[DataProvider('platformDsnProvider')]
    public function testSchemeCreatesPlatform(string $dsn): void
    {
        $factory = new PlatformFactory($this->createStub(EventDispatcherInterface::class), $this->createDsnParser());

        self::assertInstanceOf(PlatformInterface::class, $factory->fromDsn($this->createDsnParser()->parse($dsn)));
    }

    public function testFailoverSchemeIgnoresEmptyNestedDsns(): void
    {
        $dsn = new DsnDto(scheme: 'failover', query: ['platforms' => ['', 'openai://sk-test@default']]);

        $factory = new PlatformFactory($this->createStub(EventDispatcherInterface::class), $this->createDsnParser());

        self::assertInstanceOf(FailoverPlatform::class, $factory->fromDsn($dsn));
    }

    public function testFailoverSchemeAcceptsSingleNestedDsnString(): void
    {
        $dsn = new DsnDto(scheme: 'failover', query: ['platforms' => 'openai://sk-test@default']);

        $factory = new PlatformFactory($this->createStub(EventDispatcherInterface::class), $this->createDsnParser());

        self::assertInstanceOf(FailoverPlatform::class, $factory->fromDsn($dsn));
    }

    private function createDsnParser(): DsnParser
    {
        return new DsnParser($this->createStub(EventDispatcherInterface::class));
    }
}
