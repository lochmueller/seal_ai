<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit;

use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Local\InMemoryStore;
use Symfony\AI\Store\Document\Vectorizer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

abstract class AbstractTest extends UnitTestCase
{
    public function getStore()
    {
        return new InMemoryStore();
    }

    public function getVectorizer()
    {
        return new Vectorizer(new class implements PlatformInterface {
            public function invoke(string $model, object|array|string $input, array $options = []): DeferredResult
            {

                // Dummy new Vector([0.1, 0.2, 0.3]);

                // TODO: Implement invoke() method.
            }

            public function getModelCatalog(): ModelCatalogInterface
            {
                return new class extends AbstractModelCatalog {};
            }
        }, 'dummy');
    }

}
