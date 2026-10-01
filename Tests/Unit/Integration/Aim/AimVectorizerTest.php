<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit\Integration\Aim;

use Lochmueller\SealAi\Integration\Aim\AimClient;
use Lochmueller\SealAi\Integration\Aim\AimVectorizer;
use Lochmueller\SealAi\Tests\Unit\AbstractTest;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\VectorDocument;

class AimVectorizerTest extends AbstractTest
{
    public function testVectorizeStringReturnsVector(): void
    {
        $client = $this->createMock(AimClient::class);
        $client->expects(self::once())
            ->method('embed')
            ->with(['hello'], 'openai:text-embedding-3-small', 768)
            ->willReturn([[0.1, 0.2]]);

        $vector = (new AimVectorizer($client, 'openai:text-embedding-3-small', 768))->vectorize('hello');

        self::assertInstanceOf(Vector::class, $vector);
        self::assertSame([0.1, 0.2], $vector->getData());
    }

    public function testVectorizeDocumentsReturnsVectorDocumentsInOrder(): void
    {
        $client = $this->createMock(AimClient::class);
        $client->expects(self::once())
            ->method('embed')
            ->with(['first', 'second'])
            ->willReturn([[0.1], [0.2]]);

        $documents = [
            new TextDocument('id-1', 'first', new Metadata(['title' => 'First'])),
            new TextDocument('id-2', 'second'),
        ];

        $result = (new AimVectorizer($client))->vectorize($documents);

        self::assertCount(2, $result);
        self::assertInstanceOf(VectorDocument::class, $result[0]);
        self::assertSame('id-1', $result[0]->getId());
        self::assertSame([0.1], $result[0]->getVector()->getData());
        self::assertSame('First', $result[0]->getMetadata()['title']);
        self::assertSame('id-2', $result[1]->getId());
        self::assertSame([0.2], $result[1]->getVector()->getData());
    }

    public function testVectorizeEmptyArrayDoesNotCallAim(): void
    {
        $client = $this->createMock(AimClient::class);
        $client->expects(self::never())->method('embed');

        self::assertSame([], (new AimVectorizer($client))->vectorize([]));
    }
}
