<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit;

use Lochmueller\Seal\DsnParser;
use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\SealAi\AiBridge;
use Lochmueller\SealAi\Factory\PlatformFactory;
use Lochmueller\SealAi\Factory\StoreFactory;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

class AiBridgeTest extends AbstractTest
{
    public function testInitializeSetsStoreAndPlatformAndVectorizer(): void
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
        self::assertInstanceOf(PlatformInterface::class, $bridge->getPlatform());
        self::assertInstanceOf(VectorizerInterface::class, $bridge->getVectorizer());
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
        self::assertSame($platform, $bridge->getPlatform());
    }
}
