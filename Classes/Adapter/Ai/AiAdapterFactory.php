<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Adapter\Ai;

use CmsIg\Seal\Adapter\AdapterFactoryInterface;
use CmsIg\Seal\Adapter\AdapterInterface;
use Lochmueller\SealAi\AiBridge;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('seal.adapter_factory')]
class AiAdapterFactory implements AdapterFactoryInterface
{
    public function __construct(private AiBridge $aiBridge, private AiAdapter $adapter) {}

    public static function getName(): string
    {
        return 'ai';
    }

    public function createAdapter(array $dsn): AdapterInterface
    {
        $this->aiBridge->initialize($dsn);
        return $this->adapter;
    }
}
