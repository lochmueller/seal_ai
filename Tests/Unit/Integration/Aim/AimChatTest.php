<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Integration\Aim;

use Lochmueller\SealAi\Integration\Aim\AimChat;
use Lochmueller\SealAi\Integration\Aim\AimClient;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;

class AimChatTest extends AbstractTest
{
    public function testCompleteDelegatesToClientWithProvider(): void
    {
        $client = $this->createMock(AimClient::class);
        $client->expects(self::once())
            ->method('complete')
            ->with('system', 'user', 'openai:gpt-4o')
            ->willReturn('answer');

        self::assertSame('answer', (new AimChat($client, 'openai:gpt-4o'))->complete('system', 'user'));
    }

    public function testCompleteUsesDefaultProviderWhenNoneGiven(): void
    {
        $client = $this->createMock(AimClient::class);
        $client->expects(self::once())
            ->method('complete')
            ->with('system', 'user', '')
            ->willReturn('answer');

        (new AimChat($client))->complete('system', 'user');
    }
}
