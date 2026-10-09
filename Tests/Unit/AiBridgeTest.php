<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit;

use Lochmueller\Seal\DsnParser;
use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\SealAi\AiBridge;
use Lochmueller\SealAi\Chat\ChatInterface;
use Lochmueller\SealAi\Chat\PlatformChat;
use Lochmueller\SealAi\Factory\PlatformFactory;
use Lochmueller\SealAi\Factory\StoreFactory;
use Lochmueller\SealAi\Integration\Aim\AimFactory;
use Lochmueller\SealAi\Vectorizer\CachingVectorizer;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

class AiBridgeTest extends AbstractTest
{
    public function testInitializeSetsStoreAndVectorizer(): void
    {
        $store = $this->getStore();
        $platform = $this->createStub(PlatformInterface::class);

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
        $platformFactory->method('fromDsn')->willReturn($platform);

        $site = $this->createStub(Site::class);
        $site->method('getConfiguration')->willReturn([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'openai://key@default?model=text-embedding-3-small',
        ]);

        $bridge = new AiBridge($platformFactory, $storeFactory, $dsnParser);
        $bridge->initialize($site);

        self::assertInstanceOf(StoreInterface::class, $bridge->getStore());
        self::assertInstanceOf(ManagedStoreInterface::class, $bridge->getStore());
        self::assertInstanceOf(VectorizerInterface::class, $bridge->getVectorizer());
        self::assertNull($bridge->getChat());
    }

    public function testInitializeThrowsExceptionWhenModelIsMissing(): void
    {
        $store = $this->getStore();
        $platform = $this->createStub(PlatformInterface::class);

        $storeDsn = new DsnDto(scheme: 'memory');
        $platformDsn = new DsnDto(scheme: 'openai', user: 'key', query: []);

        $dsnParser = $this->createStub(DsnParser::class);
        $dsnParser->method('parse')->willReturnCallback(
            fn(string $dsn): DsnDto => match ($dsn) {
                'memory://default' => $storeDsn,
                default => $platformDsn,
            }
        );

        $storeFactory = $this->createStub(StoreFactory::class);
        $storeFactory->method('fromDsn')->willReturn($store);

        $platformFactory = $this->createStub(PlatformFactory::class);
        $platformFactory->method('fromDsn')->willReturn($platform);

        $site = $this->createStub(Site::class);
        $site->method('getConfiguration')->willReturn([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'openai://key@default',
        ]);

        $bridge = new AiBridge($platformFactory, $storeFactory, $dsnParser);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1739091202);
        $this->expectExceptionMessage('Missing "model" query parameter');

        $bridge->initialize($site);
    }

    public function testInitializeThrowsExceptionWhenModelIsEmptyString(): void
    {
        $store = $this->getStore();
        $platform = $this->createStub(PlatformInterface::class);

        $storeDsn = new DsnDto(scheme: 'memory');
        $platformDsn = new DsnDto(scheme: 'openai', user: 'key', query: ['model' => '']);

        $dsnParser = $this->createStub(DsnParser::class);
        $dsnParser->method('parse')->willReturnCallback(
            fn(string $dsn): DsnDto => match ($dsn) {
                'memory://default' => $storeDsn,
                default => $platformDsn,
            }
        );

        $storeFactory = $this->createStub(StoreFactory::class);
        $storeFactory->method('fromDsn')->willReturn($store);

        $platformFactory = $this->createStub(PlatformFactory::class);
        $platformFactory->method('fromDsn')->willReturn($platform);

        $site = $this->createStub(Site::class);
        $site->method('getConfiguration')->willReturn([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'openai://key@default?model=',
        ]);

        $bridge = new AiBridge($platformFactory, $storeFactory, $dsnParser);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1739091202);

        $bridge->initialize($site);
    }

    public function testGettersReturnInitializedValues(): void
    {
        $store = $this->getStore();
        $platform = $this->createStub(PlatformInterface::class);

        $platformDsn = new DsnDto(scheme: 'openai', user: 'key', query: ['model' => 'my-model']);

        $dsnParser = $this->createStub(DsnParser::class);
        $dsnParser->method('parse')->willReturn($platformDsn);

        $storeFactory = $this->createStub(StoreFactory::class);
        $storeFactory->method('fromDsn')->willReturn($store);

        $platformFactory = $this->createStub(PlatformFactory::class);
        $platformFactory->method('fromDsn')->willReturn($platform);

        $site = $this->createStub(Site::class);
        $site->method('getConfiguration')->willReturn([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'openai://key@default?model=my-model',
        ]);

        $bridge = new AiBridge($platformFactory, $storeFactory, $dsnParser);
        $bridge->initialize($site);

        self::assertSame($store, $bridge->getStore());
    }

    public function testInitializeCreatesPlatformChatWhenChatModelIsConfigured(): void
    {
        $platformDsn = new DsnDto(scheme: 'openai', user: 'key', query: ['model' => 'my-model']);

        $dsnParser = $this->createStub(DsnParser::class);
        $dsnParser->method('parse')->willReturn($platformDsn);

        $storeFactory = $this->createStub(StoreFactory::class);
        $storeFactory->method('fromDsn')->willReturn($this->getStore());

        $platformFactory = $this->createStub(PlatformFactory::class);
        $platformFactory->method('fromDsn')->willReturn($this->createStub(PlatformInterface::class));

        $site = $this->createStub(Site::class);
        $site->method('getConfiguration')->willReturn([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'openai://key@default?model=my-model',
            'sealAiChatModel' => 'gpt-4o',
        ]);

        $bridge = new AiBridge($platformFactory, $storeFactory, $dsnParser);
        $bridge->initialize($site);

        self::assertInstanceOf(PlatformChat::class, $bridge->getChat());
    }

    public function testInitializeUsesAimFactoryForAimScheme(): void
    {
        $aimDsn = new DsnDto(scheme: 'aim', host: 'default', query: ['model' => 'openai:text-embedding-3-small']);
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

        $site = $this->createStub(Site::class);
        $site->method('getConfiguration')->willReturn([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'aim://default?model=openai:text-embedding-3-small',
            'sealAiChatModel' => 'default',
        ]);

        $bridge = new AiBridge($platformFactory, $storeFactory, $dsnParser, $aimFactory);
        $bridge->initialize($site);

        self::assertSame($vectorizer, $bridge->getVectorizer());
        self::assertSame($chat, $bridge->getChat());
    }

    public function testInitializeThrowsExceptionForAimSchemeWithoutAim(): void
    {
        $dsnParser = $this->createStub(DsnParser::class);
        $dsnParser->method('parse')->willReturn(new DsnDto(scheme: 'aim', host: 'default'));

        $storeFactory = $this->createStub(StoreFactory::class);
        $storeFactory->method('fromDsn')->willReturn($this->getStore());

        $site = $this->createStub(Site::class);
        $site->method('getConfiguration')->willReturn([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'aim://default',
        ]);

        $bridge = new AiBridge($this->createStub(PlatformFactory::class), $storeFactory, $dsnParser);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1759312002);

        $bridge->initialize($site);
    }

    public function testIndexVectorizerUsesEmbeddingCache(): void
    {
        $bridge = $this->createInitializedBridge($this->createStub(FrontendInterface::class), []);

        self::assertInstanceOf(CachingVectorizer::class, $bridge->getIndexVectorizer());
        self::assertNotInstanceOf(CachingVectorizer::class, $bridge->getVectorizer());
    }

    public function testIndexVectorizerWithoutCacheIsPlainVectorizer(): void
    {
        $bridge = $this->createInitializedBridge(null, []);

        self::assertSame($bridge->getVectorizer(), $bridge->getIndexVectorizer());
    }

    public function testIndexVectorizerWithDisabledCacheIsPlainVectorizer(): void
    {
        $bridge = $this->createInitializedBridge($this->createStub(FrontendInterface::class), ['sealAiEmbeddingCache' => false]);

        self::assertSame($bridge->getVectorizer(), $bridge->getIndexVectorizer());
    }

    /**
     * @param array<string, mixed> $additionalConfiguration
     */
    private function createInitializedBridge(?FrontendInterface $cache, array $additionalConfiguration): AiBridge
    {
        $dsnParser = $this->createStub(DsnParser::class);
        $dsnParser->method('parse')->willReturn(new DsnDto(scheme: 'openai', user: 'key', query: ['model' => 'my-model']));

        $storeFactory = $this->createStub(StoreFactory::class);
        $storeFactory->method('fromDsn')->willReturn($this->getStore());

        $platformFactory = $this->createStub(PlatformFactory::class);
        $platformFactory->method('fromDsn')->willReturn($this->createStub(PlatformInterface::class));

        $site = $this->createStub(Site::class);
        $site->method('getConfiguration')->willReturn([
            'sealAiStoreDsn' => 'memory://default',
            'sealAiPlatformDsn' => 'openai://key@default?model=my-model',
            ...$additionalConfiguration,
        ]);

        $bridge = new AiBridge($platformFactory, $storeFactory, $dsnParser, null, $cache);
        $bridge->initialize($site);

        return $bridge;
    }
}
