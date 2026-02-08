<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Adapter\Ai;

use CmsIg\Seal\Adapter\AdapterFactoryInterface;
use CmsIg\Seal\Adapter\AdapterInterface;
use Lochmueller\SealAi\AiBridge;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

#[AutoconfigureTag('seal.adapter_factory')]
class AiAdapterFactory implements AdapterFactoryInterface
{
    public function __construct(
        private AiBridge               $aiBridge,
        private AiAdapter              $adapter,
        private ServerRequestInterface $request,
    ) {}

    public static function getName(): string
    {
        return 'ai';
    }

    public function createAdapter(array $dsn): AdapterInterface
    {
        /** @var SiteInterface $site */
        $site = $this->request->getAttribute('site');

        $site instanceof Site or throw new \RuntimeException('No site found in current request for AI adapter build', 1236891231);

        $this->aiBridge->initialize($site);
        return $this->adapter;
    }
}
