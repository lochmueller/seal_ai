<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Factory;

use Lochmueller\Seal\DsnParser;
use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\SealAi\Chat\ChatInterface;
use Lochmueller\SealAi\Chat\PlatformChat;
use Lochmueller\SealAi\Factory\AiBridgeFactory;
use Lochmueller\SealAi\Factory\PlatformFactory;
use Lochmueller\SealAi\Factory\StoreFactory;
use Lochmueller\SealAi\Integration\Aim\AimFactory;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Lochmueller\SealAi\Vectorizer\CachingVectorizer;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

class AiBridgeFactoryTest extends AbstractTest
{
    public function testForSiteSetsStoreAndVectorizer(): void
    {
        $store = $this->getStore();

        $storeDsn = new DsnDto(scheme: 'memory');
        $platformDsn = new DsnDto(scheme: 'openai', user: 'key', query: ['model' => 'text-embedding-3-small']);

        $dsnParser = $this->createStub(DsnParser::class);
        $dsnParser->method('parse')->willReturnCallback(
            fn(string $dsn): DsnDto => match ($dsn) {
                'memory://default' => $storeDsn,
                'openai://key@default?model=text-embedding-3-small' => $platformDsn,
                default => throw new \InvalidArgumentException('Unexpected DSN: ' . $dsn),
            }
        );

        $storeFactory = $this->createStub(StoreFactory::class);
        $storeFactory->method('fromDsn')->willReturn($store);

        $platformFactory = $this->createStub(PlatformFactory::class);
        $platformFactory->method('fromDsn')->willReturn($this->createStub(PlatformInterface::class));

        $site = $this->createSite([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'openai://key@default?model=text-embedding-3-small',
        ]);

        $bridge = (new AiBridgeFactory($platformFactory, $storeFactory, $dsnParser))->forSite($site);

        self::assertInstanceOf(StoreInterface::class, $bridge->getStore());
        self::assertInstanceOf(ManagedStoreInterface::class, $bridge->getStore());
        self::assertInstanceOf(VectorizerInterface::class, $bridge->getVectorizer());
        self::assertNull($bridge->getChat());
    }

    public function testForSiteThrowsExceptionWhenStoreDsnIsMissing(): void
    {
        $site = $this->createSite(['sealAiPlatformDsn' => 'openai://key@default?model=my-model']);
        $factory = $this->createFactory();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1739091203);
        $this->expectExceptionMessage('Missing "sealAiStoreDsn"');

        $factory->forSite($site);
    }

    public function testForSiteThrowsExceptionWhenPlatformDsnIsMissing(): void
    {
        $site = $this->createSite(['sealAiStoreDsn' => 'memory://default']);
        $factory = $this->createFactory();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1739091204);
        $this->expectExceptionMessage('Missing "sealAiPlatformDsn"');

        $factory->forSite($site);
    }

    public function testForSiteThrowsExceptionWhenModelIsMissing(): void
    {
        $site = $this->createSite([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'openai://key@default',
        ]);
        $factory = $this->createFactory(new DsnDto(scheme: 'openai', user: 'key', query: []));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1739091202);
        $this->expectExceptionMessage('Missing "model" query parameter');

        $factory->forSite($site);
    }

    public function testForSiteThrowsExceptionWhenModelIsEmptyString(): void
    {
        $site = $this->createSite([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'openai://key@default?model=',
        ]);
        $factory = $this->createFactory(new DsnDto(scheme: 'openai', user: 'key', query: ['model' => '']));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1739091202);

        $factory->forSite($site);
    }

    public function testForSiteCreatesPlatformChatWhenChatModelIsConfigured(): void
    {
        $site = $this->createSite([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'openai://key@default?model=my-model',
            'sealAiChatModel' => 'gpt-4o',
        ]);
        $factory = $this->createFactory();

        self::assertInstanceOf(PlatformChat::class, $factory->forSite($site)->getChat());
    }

    public function testForSiteUsesAimFactoryForAimScheme(): void
    {
        $aimDsn = new DsnDto(scheme: 'ai', host: 'aim', query: ['model' => 'openai:text-embedding-3-small']);
        $vectorizer = $this->createStub(VectorizerInterface::class);
        $chat = $this->createStub(ChatInterface::class);

        $dsnParser = $this->createStub(DsnParser::class);
        $dsnParser->method('parse')->willReturn($aimDsn);

        $storeFactory = $this->createStub(StoreFactory::class);
        $storeFactory->method('fromDsn')->willReturn($this->getStore());

        $platformFactory = $this->createMock(PlatformFactory::class);
        $platformFactory->expects(self::never())->method('fromDsn');

        $aimFactory = $this->createMock(AimFactory::class);
        $aimFactory->expects(self::once())->method('createVectorizer')->with($aimDsn)->willReturn($vectorizer);
        $aimFactory->expects(self::once())->method('createChat')->with('default')->willReturn($chat);

        $site = $this->createSite([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'ai://aim?model=openai:text-embedding-3-small',
            'sealAiChatModel' => 'default',
        ]);

        $bridge = (new AiBridgeFactory($platformFactory, $storeFactory, $dsnParser, $aimFactory))->forSite($site);

        self::assertSame($vectorizer, $bridge->getVectorizer());
        self::assertSame($chat, $bridge->getChat());
    }

    public function testForSiteThrowsExceptionForAimSchemeWithoutAim(): void
    {
        $site = $this->createSite([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'ai://aim',
        ]);
        $factory = $this->createFactory(new DsnDto(scheme: 'ai', host: 'aim'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1759312002);

        $factory->forSite($site);
    }

    public function testCachedVectorizerUsesEmbeddingCache(): void
    {
        $site = $this->createSite([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'openai://key@default?model=my-model',
        ]);
        $factory = $this->createFactory(cache: $this->createStub(FrontendInterface::class));

        $bridge = $factory->forSite($site);

        self::assertInstanceOf(CachingVectorizer::class, $bridge->getCachedVectorizer());
        self::assertNotInstanceOf(CachingVectorizer::class, $bridge->getVectorizer());
    }

    public function testCachedVectorizerWithoutCacheIsPlainVectorizer(): void
    {
        $site = $this->createSite([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'openai://key@default?model=my-model',
        ]);
        $factory = $this->createFactory();

        $bridge = $factory->forSite($site);

        self::assertSame($bridge->getVectorizer(), $bridge->getCachedVectorizer());
    }

    public function testCachedVectorizerWithDisabledCacheIsPlainVectorizer(): void
    {
        $site = $this->createSite([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'openai://key@default?model=my-model',
            'sealAiEmbeddingCache' => false,
        ]);
        $factory = $this->createFactory(cache: $this->createStub(FrontendInterface::class));

        $bridge = $factory->forSite($site);

        self::assertSame($bridge->getVectorizer(), $bridge->getCachedVectorizer());
    }

    public function testSameSiteIsBuiltOnlyOnce(): void
    {
        $storeFactory = $this->createMock(StoreFactory::class);
        $storeFactory->expects(self::once())->method('fromDsn')->willReturn($this->getStore());

        $dsnParser = $this->createStub(DsnParser::class);
        $dsnParser->method('parse')->willReturn(new DsnDto(scheme: 'openai', user: 'key', query: ['model' => 'my-model']));

        $platformFactory = $this->createStub(PlatformFactory::class);
        $platformFactory->method('fromDsn')->willReturn($this->createStub(PlatformInterface::class));

        $site = $this->createSite([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'openai://key@default?model=my-model',
        ]);

        $factory = new AiBridgeFactory($platformFactory, $storeFactory, $dsnParser);

        self::assertSame($factory->forSite($site), $factory->forSite($site));
    }

    public function testEverySiteGetsItsOwnBridgeAndStore(): void
    {
        $firstStore = $this->getStore();
        $secondStore = $this->getStore();

        $dsnParser = $this->createStub(DsnParser::class);
        $dsnParser->method('parse')->willReturnCallback(
            static fn(string $dsn): DsnDto => str_starts_with($dsn, 'openai')
                ? new DsnDto(scheme: 'openai', user: 'key', query: ['model' => 'my-model'])
                : new DsnDto(scheme: 'memory', host: $dsn)
        );

        $storeFactory = $this->createStub(StoreFactory::class);
        $storeFactory->method('fromDsn')->willReturnOnConsecutiveCalls($firstStore, $secondStore);

        $platformFactory = $this->createStub(PlatformFactory::class);
        $platformFactory->method('fromDsn')->willReturn($this->createStub(PlatformInterface::class));

        $factory = new AiBridgeFactory($platformFactory, $storeFactory, $dsnParser);

        $first = $factory->forSite($this->createSite([
            'sealAiStoreDsn' => 'memory://first',
            'sealAiPlatformDsn' => 'openai://key@default?model=my-model',
        ], 'first'));
        $second = $factory->forSite($this->createSite([
            'sealAiStoreDsn' => 'memory://second',
            'sealAiPlatformDsn' => 'openai://key@default?model=my-model',
        ], 'second'));

        self::assertNotSame($first, $second);
        self::assertSame($firstStore, $first->getStore());
        self::assertSame($secondStore, $second->getStore());
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createFactory(
        ?DsnDto $platformDsn = null,
        ?FrontendInterface $cache = null,
    ): AiBridgeFactory {
        $dsnParser = $this->createStub(DsnParser::class);
        $dsnParser->method('parse')->willReturn(
            $platformDsn ?? new DsnDto(scheme: 'openai', user: 'key', query: ['model' => 'my-model'])
        );

        $storeFactory = $this->createStub(StoreFactory::class);
        $storeFactory->method('fromDsn')->willReturn($this->getStore());

        $platformFactory = $this->createStub(PlatformFactory::class);
        $platformFactory->method('fromDsn')->willReturn($this->createStub(PlatformInterface::class));

        return new AiBridgeFactory($platformFactory, $storeFactory, $dsnParser, null, $cache);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createSite(array $configuration, string $identifier = 'main'): Site
    {
        return new Site($identifier, 1, ['base' => 'https://example.com/', ...$configuration]);
    }
}
