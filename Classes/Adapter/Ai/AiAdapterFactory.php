<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Adapter\Ai;

use CmsIg\Seal\Adapter\AdapterFactoryInterface;
use CmsIg\Seal\Adapter\AdapterInterface;
use Lochmueller\Seal\Engine\EngineFactory;
use Lochmueller\SealAi\Factory\AiBridgeFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use TYPO3\CMS\Core\Site\Entity\Site;

#[AutoconfigureTag('seal.adapter_factory')]
class AiAdapterFactory implements AdapterFactoryInterface
{
    /**
     * @var array<string, AiAdapter>
     */
    private array $adapters = [];

    public function __construct(
        private readonly AiBridgeFactory          $bridgeFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public static function getName(): string
    {
        return 'ai';
    }

    public function createAdapter(array $dsn): AdapterInterface
    {
        $site = $this->resolveSite();

        // The adapter is bound to its site, because EXT:seal caches the engines per site and
        // may use the engine of site A after the engine of site B has been built.
        return $this->adapters[$site->getIdentifier()] ??= $this->buildAdapter($site);
    }

    protected function buildAdapter(Site $site): AiAdapter
    {
        $aiBridge = $this->bridgeFactory->forSite($site);

        return new AiAdapter(
            new AiSchemaManager($aiBridge),
            new AiIndexer($aiBridge),
            new AiSearcher($aiBridge, $this->eventDispatcher),
        );
    }

    protected function resolveSite(): Site
    {
        if (EngineFactory::$currentSite instanceof Site) {
            return EngineFactory::$currentSite;
        }

        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $request instanceof ServerRequestInterface or throw new \RuntimeException('No TYPO3 request available for AI adapter build', 1236891232);

        $site = $request->getAttribute('site');

        $site instanceof Site or throw new \RuntimeException('No site found in current request for AI adapter build', 1236891231);

        return $site;
    }
}
