<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\ViewHelpers;

use Lochmueller\SealAi\AiBridge;
use Lochmueller\SealAi\Chat\ChatInterface;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Lochmueller\SealAi\ViewHelpers\SgeViewHelper;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;

class SgeViewHelperTest extends AbstractTest
{
    public function testRenderReturnsEmptyStringWithoutItems(): void
    {
        $bridge = $this->createMock(AiBridge::class);
        $bridge->expects(self::never())->method('initialize');

        self::assertSame('', $this->render($bridge, [], $this->createSite('gpt-4o-mini')));
    }

    public function testRenderReturnsEmptyStringWithoutChatModel(): void
    {
        $bridge = $this->createMock(AiBridge::class);
        $bridge->expects(self::never())->method('initialize');

        self::assertSame('', $this->render($bridge, [['title' => 'A', 'content' => 'B']], $this->createSite('')));
    }

    public function testRenderReturnsEmptyStringWithoutSite(): void
    {
        $bridge = $this->createMock(AiBridge::class);
        $bridge->expects(self::never())->method('initialize');

        self::assertSame('', $this->render($bridge, [['title' => 'A', 'content' => 'B']], null));
    }

    public function testRenderReturnsSummaryWrappedInContainer(): void
    {
        $chat = $this->createMock(ChatInterface::class);
        $chat->expects(self::once())
            ->method('complete')
            ->with(
                self::isString(),
                self::callback(static fn(string $prompt): bool => str_contains($prompt, "Result 1:\nTitle: First\nContent: Lorem")
                    && str_contains($prompt, "Result 3:\nTitle: Third")
                    && !str_contains($prompt, 'Result 2:')),
            )
            ->willReturn('<p>Summary</p>');

        $site = $this->createSite('gpt-4o-mini');
        $bridge = $this->createMock(AiBridge::class);
        $bridge->expects(self::once())->method('initialize')->with($site);
        $bridge->method('getChat')->willReturn($chat);

        $items = [
            ['title' => 'First', 'content' => 'Lorem'],
            ['title' => '', 'content' => ''],
            ['title' => 'Third', 'content' => 'Ipsum'],
        ];

        self::assertSame('<div class="seal-ai-sge"><p>Summary</p></div>', $this->render($bridge, $items, $site));
    }

    public function testRenderReturnsEmptyStringWhenChatFails(): void
    {
        $chat = $this->createStub(ChatInterface::class);
        $chat->method('complete')->willThrowException(new \RuntimeException('API down'));

        $bridge = $this->createStub(AiBridge::class);
        $bridge->method('getChat')->willReturn($chat);

        self::assertSame('', $this->render($bridge, [['title' => 'A', 'content' => 'B']], $this->createSite('gpt-4o-mini')));
    }

    public function testRenderReturnsEmptyStringWithoutChat(): void
    {
        $bridge = $this->createStub(AiBridge::class);
        $bridge->method('getChat')->willReturn(null);

        self::assertSame('', $this->render($bridge, [['title' => 'A', 'content' => 'B']], $this->createSite('gpt-4o-mini')));
    }

    /**
     * @param array<mixed> $items
     */
    private function render(AiBridge $bridge, array $items, ?Site $site): string
    {
        $renderingContext = new RenderingContext();
        $renderingContext->setAttribute(
            ServerRequestInterface::class,
            (new ServerRequest('https://example.com/'))->withAttribute('site', $site),
        );

        $viewHelper = new SgeViewHelper($bridge);
        $viewHelper->setRenderingContext($renderingContext);
        $viewHelper->setArguments(['items' => $items]);

        return $viewHelper->render();
    }

    private function createSite(string $chatModel): Site
    {
        return new Site('main', 1, ['base' => 'https://example.com/', 'sealAiChatModel' => $chatModel]);
    }
}
