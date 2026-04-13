<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Tests\Unit;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\InMemory\Store;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

abstract class AbstractTest extends UnitTestCase
{
    public function getStore(): Store
    {
        return new Store();
    }

    public function getVectorizer(): Vectorizer
    {
        return new Vectorizer(new class implements PlatformInterface {
            public function invoke(string $model, object|array|string $input, array $options = []): DeferredResult
            {
                $resultConverter = new class implements ResultConverterInterface {
                    public function supports(Model $model): bool
                    {
                        return true;
                    }

                    public function convert(RawResultInterface $result, array $options = []): ResultInterface
                    {
                        return new VectorResult(new Vector([0.1, 0.2, 0.3]));
                    }

                    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
                    {
                        return null;
                    }
                };

                $rawResult = new class implements RawResultInterface {
                    public function getData(): array
                    {
                        return [];
                    }

                    public function getDataStream(): iterable
                    {
                        return [];
                    }

                    public function getObject(): object
                    {
                        return new \stdClass();
                    }
                };

                return new DeferredResult($resultConverter, $rawResult);
            }

            public function getModelCatalog(): ModelCatalogInterface
            {
                return new class extends AbstractModelCatalog {
                    /**
                     * @var array<string, array{class: class-string, capabilities: list<Capability>}>
                     */
                    protected array $models = [
                        'dummy' => [
                            'class' => Model::class,
                            'capabilities' => [],
                        ],
                    ];
                };
            }
        }, 'dummy');
    }
}
