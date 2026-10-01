<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Integration\Aim;

use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\SealAi\Integration\Aim\AimClient;
use Lochmueller\SealAi\Integration\Aim\AimFactory;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;

class AimFactoryTest extends AbstractTest
{
    public function testCreateVectorizerPassesModelAndDimensions(): void
    {
        $client = $this->createMock(AimClient::class);
        $client->expects(self::once())
            ->method('embed')
            ->with(['text'], 'openai:text-embedding-3-small', 768)
            ->willReturn([[0.5]]);

        $dsn = new DsnDto(scheme: 'aim', host: 'default', query: ['model' => 'openai:text-embedding-3-small', 'dimensions' => '768']);

        (new AimFactory($client))->createVectorizer($dsn)->vectorize('text');
    }

    public function testCreateVectorizerUsesAimDefaultsWithoutQuery(): void
    {
        $client = $this->createMock(AimClient::class);
        $client->expects(self::once())
            ->method('embed')
            ->with(['text'], '', 0)
            ->willReturn([[0.5]]);

        (new AimFactory($client))->createVectorizer(new DsnDto(scheme: 'aim', host: 'default'))->vectorize('text');
    }

    public function testCreateChatMapsDefaultToAimDefaultProvider(): void
    {
        $client = $this->createMock(AimClient::class);
        $client->expects(self::once())
            ->method('complete')
            ->with('system', 'user', '')
            ->willReturn('summary');

        self::assertSame('summary', (new AimFactory($client))->createChat(AimFactory::DEFAULT_PROVIDER)->complete('system', 'user'));
    }

    public function testCreateChatPassesProviderNotation(): void
    {
        $client = $this->createMock(AimClient::class);
        $client->expects(self::once())
            ->method('complete')
            ->with('system', 'user', 'anthropic:claude-sonnet-4')
            ->willReturn('summary');

        (new AimFactory($client))->createChat('anthropic:claude-sonnet-4')->complete('system', 'user');
    }
}
