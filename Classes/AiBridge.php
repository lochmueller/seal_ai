<?php

declare(strict_types=1);

namespace Lochmueller\SealAi;

use Lochmueller\Seal\DsnParser;
use Lochmueller\Seal\Dto\DsnDto;
use Lochmueller\SealAi\Chat\ChatInterface;
use Lochmueller\SealAi\Chat\PlatformChat;
use Lochmueller\SealAi\Factory\PlatformFactory;
use Lochmueller\SealAi\Factory\StoreFactory;
use Lochmueller\SealAi\Integration\Aim\AimFactory;
use Lochmueller\SealAi\Vectorizer\CachingVectorizer;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

class AiBridge
{
    protected VectorizerInterface $vectorizer;

    protected VectorizerInterface $indexVectorizer;

    protected ?ChatInterface $chat = null;

    protected StoreInterface&ManagedStoreInterface $store;

    public function __construct(
        private readonly PlatformFactory $platformFactory,
        private readonly StoreFactory    $storeFactory,
        private readonly DsnParser       $dsnParser,
        private readonly ?AimFactory     $aimFactory = null,
        private readonly ?FrontendInterface $embeddingCache = null,
    ) {}

    public function getStore(): StoreInterface&ManagedStoreInterface
    {
        return $this->store;
    }

    public function getVectorizer(): VectorizerInterface
    {
        return $this->vectorizer;
    }

    /**
     * Vectorizer for indexing: wrapped in the embedding cache (if enabled), so unchanged content costs no tokens.
     */
    public function getIndexVectorizer(): VectorizerInterface
    {
        return $this->indexVectorizer; // @todo rename to "cachedVectorizer" umbennen. Ggf. auch in der Search benutzen.
    }

    /**
     * Chat for AI-generated search summaries (SGE). Null if no "sealAiChatModel" is configured.
     */
    public function getChat(): ?ChatInterface
    {
        return $this->chat;
    }

    public function initialize(Site $site): void
    {
        $config = $site->getConfiguration();

        $storeDsn = $config['sealAiStoreDsn'] ?? '';
        if ($storeDsn === '') {
            throw new \RuntimeException(
                'Missing "sealAiStoreDsn" in site configuration. Please configure the AI Store DSN.',
                1739091203
            );
        }

        $platformDsn = $config['sealAiPlatformDsn'] ?? '';
        if ($platformDsn === '') {
            throw new \RuntimeException(
                'Missing "sealAiPlatformDsn" in site configuration. Please configure the AI Platform DSN.',
                1739091204
            );
        }

        $chatModel = $config['sealAiChatModel'] ?? '';
        $chatModel = \is_string($chatModel) ? $chatModel : '';

        // Store
        $dsnDto = $this->dsnParser->parse($storeDsn);
        $this->store = $this->storeFactory->fromDsn($dsnDto);

        // Platform
        $dsnDto = $this->dsnParser->parse($platformDsn);
        if ($dsnDto->scheme === 'aim') {
            $this->initializeAim($dsnDto, $chatModel);
        } else {
            $this->initializePlatform($dsnDto, $chatModel);
        }

        $this->indexVectorizer = $this->createIndexVectorizer($dsnDto, (bool) ($config['sealAiEmbeddingCache'] ?? true));
    }

    protected function initializePlatform(DsnDto $dsnDto, string $chatModel): void
    {
        $platform = $this->platformFactory->fromDsn($dsnDto);

        $model = $dsnDto->query['model'] ?? '';
        if (!\is_string($model) || $model === '') {
            throw new \RuntimeException(
                'Missing "model" query parameter in sealAiPlatformDsn. Example: openai://key@default?model=text-embedding-3-small',
                1739091202
            );
        }
        $this->vectorizer = new Vectorizer($platform, $model);
        $this->chat = $chatModel !== '' ? new PlatformChat($platform, $chatModel) : null;
    }

    protected function initializeAim(DsnDto $dsnDto, string $chatModel): void
    {
        if ($this->aimFactory === null) {
            throw new \RuntimeException(
                'Please install b13/aim (EXT:aim) to use the "aim://" platform DSN',
                1759312002
            );
        }

        $this->vectorizer = $this->aimFactory->createVectorizer($dsnDto);
        $this->chat = $chatModel !== '' ? $this->aimFactory->createChat($chatModel) : null;
    }

    protected function createIndexVectorizer(DsnDto $dsnDto, bool $cacheEnabled): VectorizerInterface
    {
        if ($this->embeddingCache === null || !$cacheEnabled) {
            return $this->vectorizer;
        }

        $model = $dsnDto->query['model'] ?? '';
        $dimensions = $dsnDto->query['dimensions'] ?? 0;

        return new CachingVectorizer(
            $this->vectorizer,
            $this->embeddingCache,
            CachingVectorizer::createFingerprint(
                $dsnDto->scheme,
                \is_string($model) ? $model : '',
                \is_numeric($dimensions) ? (int) $dimensions : 0,
            ),
        );
    }
}
