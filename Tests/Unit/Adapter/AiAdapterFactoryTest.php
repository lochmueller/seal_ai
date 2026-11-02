<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Adapter;

use CmsIg\Seal\Adapter\AdapterInterface;
use Lochmueller\SealAi\Adapter\Ai\AiAdapter;
use Lochmueller\SealAi\Adapter\Ai\AiAdapterFactory;
use Lochmueller\SealAi\AiBridge;
use Lochmueller\SealAi\Factory\AiBridgeFactory;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

class AiAdapterFactoryTest extends AbstractTest
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    public function testGetNameReturnsAi(): void
    {
        self::assertSame('ai', AiAdapterFactory::getName());
    }

    public function testCreateAdapterReturnAdapterInterface(): void
    {
        $site = $this->createSite('main');
        $this->setRequestWithSite($site);

        $bridgeFactory = $this->createMock(AiBridgeFactory::class);
        $bridgeFactory->expects(self::once())->method('forSite')->with($site)->willReturn($this->createStub(AiBridge::class));

        $result = $this->createFactory($bridgeFactory)->createAdapter(['scheme' => 'ai', 'host' => 'localhost', 'query' => []]);

        self::assertInstanceOf(AdapterInterface::class, $result);
        self::assertInstanceOf(AiAdapter::class, $result);
    }

    public function testAdapterOfTheSameSiteIsBuiltOnlyOnce(): void
    {
        $site = $this->createSite('main');
        $this->setRequestWithSite($site);

        $bridgeFactory = $this->createMock(AiBridgeFactory::class);
        $bridgeFactory->expects(self::once())->method('forSite')->willReturn($this->createStub(AiBridge::class));

        $factory = $this->createFactory($bridgeFactory);
        $dsn = ['scheme' => 'ai', 'host' => 'localhost', 'query' => []];

        self::assertSame($factory->createAdapter($dsn), $factory->createAdapter($dsn));
    }

    public function testEverySiteGetsItsOwnAdapter(): void
    {
        $first = $this->createSite('first');
        $second = $this->createSite('second');

        $bridgeFactory = $this->createStub(AiBridgeFactory::class);
        $bridgeFactory->method('forSite')->willReturn($this->createStub(AiBridge::class));

        $factory = $this->createFactory($bridgeFactory);
        $dsn = ['scheme' => 'ai', 'host' => 'localhost', 'query' => []];

        $this->setRequestWithSite($first);
        $firstAdapter = $factory->createAdapter($dsn);

        $this->setRequestWithSite($second);
        $secondAdapter = $factory->createAdapter($dsn);

        self::assertNotSame($firstAdapter, $secondAdapter);
    }

    public function testCreateAdapterThrowsExceptionWhenSiteIsNotSiteInstance(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn(null);
        $GLOBALS['TYPO3_REQUEST'] = $request;

        $bridgeFactory = $this->createMock(AiBridgeFactory::class);
        $bridgeFactory->expects(self::never())->method('forSite');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1236891231);

        $this->createFactory($bridgeFactory)->createAdapter(['scheme' => 'ai', 'host' => 'localhost', 'query' => []]);
    }

    public function testCreateAdapterThrowsExceptionWithoutRequest(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);

        $bridgeFactory = $this->createMock(AiBridgeFactory::class);
        $bridgeFactory->expects(self::never())->method('forSite');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1236891232);

        $this->createFactory($bridgeFactory)->createAdapter(['scheme' => 'ai', 'host' => 'localhost', 'query' => []]);
    }

    private function createFactory(AiBridgeFactory $bridgeFactory): AiAdapterFactory
    {
        return new AiAdapterFactory($bridgeFactory, $this->createStub(EventDispatcherInterface::class));
    }

    private function setRequestWithSite(Site $site): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn($site);
        $GLOBALS['TYPO3_REQUEST'] = $request;
    }

    private function createSite(string $identifier): Site
    {
        return new Site($identifier, 1, ['base' => 'https://example.com/']);
    }
}
