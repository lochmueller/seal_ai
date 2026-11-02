<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Factory;

use Lochmueller\Seal\DsnParser;
use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\SealAi\AiBridge;
use Lochmueller\SealAi\Chat\PlatformChat;
use Lochmueller\SealAi\Integration\Aim\AimFactory;
use Lochmueller\SealAi\Vectorizer\CachingVectorizer;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Document\VectorizerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds one AiBridge per site and keeps it by site identifier, so the expensive store and
 * platform clients are created once per request without sharing state between sites.
 */
class AiBridgeFactory
{
    /**
     * @var array<string, AiBridge>
     */
    private array $bridges = [];

    public function __construct(
        private readonly PlatformFactory    $platformFactory,
        private readonly StoreFactory       $storeFactory,
        private readonly DsnParser          $dsnParser,
        private readonly ?AimFactory        $aimFactory = null,
        private readonly ?FrontendInterface $embeddingCache = null,
    ) {}

    public function forSite(Site $site): AiBridge
    {
        return $this->bridges[$site->getIdentifier()] ??= $this->create($site);
    }

    protected function create(Site $site): AiBridge
    {
        $config = $site->getConfiguration();

        $storeDsn = $config['sealAiStoreDsn'] ?? '';
        if (!\is_string($storeDsn) || $storeDsn === '') {
            throw new \RuntimeException(
                'Missing "sealAiStoreDsn" in site configuration. Please configure the AI Store DSN.',
                1739091203
            );
        }

        $platformDsn = $config['sealAiPlatformDsn'] ?? '';
        if (!\is_string($platformDsn) || $platformDsn === '') {
            throw new \RuntimeException(
                'Missing "sealAiPlatformDsn" in site configuration. Please configure the AI Platform DSN.',
                1739091204
            );
        }

        $chatModel = $config['sealAiChatModel'] ?? '';
        $chatModel = \is_string($chatModel) ? $chatModel : '';

        $store = $this->storeFactory->fromDsn($this->dsnParser->parse($storeDsn));

        $dsnDto = $this->dsnParser->parse($platformDsn);
        if ($this->isAimDsn($dsnDto)) {
            $vectorizer = $this->getAimFactory()->createVectorizer($dsnDto);
            $chat = $chatModel !== '' ? $this->getAimFactory()->createChat($chatModel) : null;
        } else {
            $platform = $this->platformFactory->fromDsn($dsnDto);
            $vectorizer = new Vectorizer($platform, $this->getEmbeddingModel($dsnDto));
            $chat = $chatModel !== '' ? new PlatformChat($platform, $chatModel) : null;
        }

        return new AiBridge(
            $store,
            $vectorizer,
            $this->createCachedVectorizer($vectorizer, $dsnDto, (bool) ($config['sealAiEmbeddingCache'] ?? true)),
            $chat,
        );
    }

    protected function getEmbeddingModel(DsnDto $dsnDto): string
    {
        $model = $dsnDto->query['model'] ?? '';
        if (!\is_string($model) || $model === '') {
            throw new \RuntimeException(
                'Missing "model" query parameter in sealAiPlatformDsn. Example: openai://key@default?model=text-embedding-3-small',
                1739091202
            );
        }

        return $model;
    }

    protected function getAimFactory(): AimFactory
    {
        return $this->aimFactory ?? throw new \RuntimeException(
            'Please install b13/aim (EXT:aim) to use the "ai://aim" platform DSN',
            1759312002
        );
    }

    /**
     * AiM is addressed via "ai://aim" (scheme "ai", host "aim").
     */
    protected function isAimDsn(DsnDto $dsnDto): bool
    {
        return $dsnDto->scheme === 'ai' && $dsnDto->host === 'aim';
    }

    protected function createCachedVectorizer(VectorizerInterface $vectorizer, DsnDto $dsnDto, bool $cacheEnabled): VectorizerInterface
    {
        if ($this->embeddingCache === null || !$cacheEnabled) {
            return $vectorizer;
        }

        $model = $dsnDto->query['model'] ?? '';
        $dimensions = $dsnDto->query['dimensions'] ?? 0;

        return new CachingVectorizer(
            $vectorizer,
            $this->embeddingCache,
            CachingVectorizer::createFingerprint(
                $this->isAimDsn($dsnDto) ? 'aim' : $dsnDto->scheme,
                \is_string($model) ? $model : '',
                \is_numeric($dimensions) ? (int) $dimensions : 0,
            ),
        );
    }
}
