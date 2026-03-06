<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Adapter;

use CmsIg\Seal\Adapter\AdapterInterface;
use Lochmueller\SealAi\Adapter\Ai\AiAdapter;
use Lochmueller\SealAi\Adapter\Ai\AiAdapterFactory;
use Lochmueller\SealAi\AiBridge;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

class AiAdapterFactoryTest extends AbstractTest
{
    public function testGetNameReturnsAi(): void
    {
        self::assertSame('ai', AiAdapterFactory::getName());
    }

    public function testCreateAdapterReturnAdapterInterface(): void
    {
        $site = $this->createStub(Site::class);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->with('site')->willReturn($site);
        $GLOBALS['TYPO3_REQUEST'] = $request;

        $aiBridge = $this->createMock(AiBridge::class);
        $aiBridge->expects(self::once())->method('initialize')->with($site);

        $adapter = $this->createStub(AiAdapter::class);

        $factory = new AiAdapterFactory($aiBridge, $adapter);
        $result = $factory->createAdapter(['ai://localhost']);

        self::assertInstanceOf(AdapterInterface::class, $result);
    }

    public function testCreateAdapterThrowsExceptionWhenSiteIsNotSiteInstance(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1236891231);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->with('site')->willReturn(null);
        $GLOBALS['TYPO3_REQUEST'] = $request;

        $aiBridge = $this->createStub(AiBridge::class);
        $adapter = $this->createStub(AiAdapter::class);

        $factory = new AiAdapterFactory($aiBridge, $adapter);
        $factory->createAdapter(['ai://localhost']);
    }
}
