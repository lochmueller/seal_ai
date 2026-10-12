<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Chat;

use Lochmueller\SealAi\Chat\ChatInterface;
use Lochmueller\SealAi\Chat\PlatformChat;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

class PlatformChatTest extends AbstractTest
{
    public function testCompleteSendsSystemAndUserMessageAndReturnsText(): void
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects(self::once())
            ->method('invoke')
            ->with(
                'gpt-4o-mini',
                self::callback(static function (MessageBag $messages): bool {
                    $list = $messages->getMessages();

                    return \count($list) === 2
                        && $list[0]->getRole() === Role::System
                        && $list[0]->getContent() === 'system prompt'
                        && $list[1]->getRole() === Role::User;
                }),
            )
            ->willReturn($this->createTextResult('summary'));

        $chat = new PlatformChat($platform, 'gpt-4o-mini');

        self::assertInstanceOf(ChatInterface::class, $chat);
        self::assertSame('summary', $chat->complete('system prompt', 'user prompt'));
    }

    private function createTextResult(string $text): DeferredResult
    {
        $resultConverter = new class ($text) implements ResultConverterInterface {
            public function __construct(private readonly string $text) {}

            public function supports(Model $model): bool
            {
                return true;
            }

            public function convert(RawResultInterface $result, array $options = []): ResultInterface
            {
                return new TextResult($this->text);
            }

            public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
            {
                return null;
            }
        };

        $rawResult = $this->createStub(RawResultInterface::class);

        return new DeferredResult($resultConverter, $rawResult);
    }
}
