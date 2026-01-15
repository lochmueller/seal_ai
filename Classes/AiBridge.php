<?php

declare(strict_types=1);

namespace Lochmueller\SealAi;

use Lochmueller\SealAi\Factory\PlatformFactory;
use Lochmueller\SealAi\Factory\StoreFactory;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Bridge\MariaDb\Store;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

class AiBridge
{
    protected VectorizerInterface $vectorizer;

    protected PlatformInterface $platform;

    protected StoreInterface&ManagedStoreInterface $store;

    public function __construct(
        private readonly PlatformFactory $platformFactory,
        private readonly StoreFactory    $storeFactory,
        private readonly DsnParser       $dsnParser,
    ) {}

    public function getStore(): StoreInterface&ManagedStoreInterface
    {
        return $this->store;
    }

    public function getVectorizer(): VectorizerInterface
    {
        return $this->vectorizer;
    }

    public function getPlatform(): PlatformInterface
    {
        return $this->platform;
    }

    public function initialize(Site $site): void
    {
        $config = $site->getConfiguration();
        // Store
        $dsnDto = $this->dsnParser->parse($config['sealAiPlatformDsn'] ?? '');
        $this->store = $this->storeFactory->fromDsn($dsnDto);

        // Platform
        $dsnDto = $this->dsnParser->parse($config['sealAiStoreDsn'] ?? '');
        $this->platform = $this->platformFactory->fromDsn($dsnDto);
        $this->vectorizer = new Vectorizer($this->platform, $dsnDto->query['model'] ?? '');
    }

}
