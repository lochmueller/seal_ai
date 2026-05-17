<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Factory;

use Lochmueller\Seal\Dto\DsnDto;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Bridge\OpenRouter as OpenRouterBridge;
use Symfony\AI\Platform\Bridge\OpenAi as OpenAiBridge;
use Symfony\AI\Platform\Bridge\Anthropic as AnthropicBridge;
use Symfony\AI\Platform\Bridge\Gemini as GeminiBridge;
use Symfony\AI\Platform\Bridge\VertexAi as VertexAiBridge;
use Symfony\AI\Platform\Bridge\Mistral as MistralBridge;
use Symfony\AI\Platform\Bridge\Ollama as OllamaBridge;
use Symfony\AI\Platform\Bridge\HuggingFace as HuggingFaceBridge;
use Symfony\AI\Platform\Bridge\Replicate as ReplicateBridge;
use Symfony\AI\Platform\Bridge\LmStudio as LmStudioBridge;
use Symfony\AI\Platform\Bridge\Albert as AlbertBridge;
use Symfony\AI\Platform\Bridge\Cartesia as CartesiaBridge;
use Symfony\AI\Platform\Bridge\ElevenLabs as ElevenLabsBridge;
use Symfony\AI\Platform\Bridge\Perplexity as PerplexityBridge;
use Symfony\AI\Platform\Bridge\Scaleway as ScalewayBridge;
use Symfony\AI\Platform\Bridge\Voyage as VoyageBridge;
use Symfony\AI\Platform\Bridge\DeepSeek as DeepSeekBridge;
use Symfony\AI\Platform\Bridge\Cerebras as CerebrasBridge;
use Symfony\AI\Platform\Bridge\Decart as DecartBridge;
use Symfony\AI\Platform\Bridge\AiMlApi as AiMlApiBridge;
use Symfony\AI\Platform\Bridge\DockerModelRunner as DockerModelRunnerBridge;
use Symfony\AI\Platform\Bridge\TransformersPhp as TransformersPhpBridge;
use Symfony\AI\Platform\Bridge\Generic as GenericBridge;
use Symfony\AI\Platform\Bridge\Azure as AzureBridge;
use Symfony\AI\Platform\Bridge\Bedrock as BedrockBridge;
use Symfony\Component\HttpClient\HttpClient;
use Lochmueller\SealAi\Event\CreatePlatformEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @see https://github.com/symfony/ai/issues/402
 */
class PlatformFactory
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function fromDsn(DsnDto $dsn): PlatformInterface
    {
        $apiKey = $dsn->user ?? '';
        $client = HttpClient::create();

        // DSN Examples:
        // openai://api-key@default
        // anthropic://api-key@default
        // gemini://api-key@default
        // openrouter://api-key@default
        // vertex://location/project-id?api_key=api-key
        // bedrock://default
        // mistral://api-key@default
        // ollama://host:11434
        // huggingface://api-key@default?provider=hf_inference
        // replicate://api-key@default
        // lmstudio://host:1234
        // albert://api-key@host
        // cartesia://api-key@default?version=v1
        // elevenlabs://api-key@host
        // perplexity://api-key@default
        // scaleway://api-key@default
        // voyage://api-key@default
        // deepseek://api-key@default
        // cerebras://api-key@default
        // decart://api-key@host
        // aimlapi://api-key@host
        // docker://host:12434
        // transformers://
        // generic://host?api_key=api-key
        // azure-openai://api-key@host?deployment=deployment&api_version=api-version
        // azure-meta://api-key@host

        switch ($dsn->scheme) {
            case 'event':
                $event = $this->eventDispatcher->dispatch(new CreatePlatformEvent($dsn));
                return $event->getPlatform() ?? throw new \RuntimeException('No platform provided by event listener for DSN scheme "event"', 1739091200);

            case 'openai':
                class_exists(OpenAiBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-open-ai-platform to use OpenAI platform');
                return OpenAiBridge\Factory::createPlatform($apiKey, $client);

            case 'anthropic':
                class_exists(AnthropicBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-anthropic-platform to use Anthropic platform');
                return AnthropicBridge\Factory::createPlatform($apiKey, $client);

            case 'gemini':
                class_exists(GeminiBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-gemini-platform to use Gemini platform');
                return GeminiBridge\Factory::createPlatform($apiKey, $client);

            case 'openrouter':
                class_exists(OpenRouterBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-open-router-platform to use OpenRouter platform');
                return OpenRouterBridge\Factory::createPlatform($apiKey, $client);

            case 'vertex':
            case 'vertexai':
                class_exists(VertexAiBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-vertex-ai-platform to use VertexAI platform');
                $location = $dsn->host ?? $dsn->query['location'] ?? '';
                $projectId = $dsn->query['project_id'] ?? '';
                $vertexApiKey = $dsn->query['api_key'] ?? $apiKey;
                return VertexAiBridge\Factory::createPlatform($location, $projectId, $vertexApiKey, $client);

            case 'bedrock':
                class_exists(BedrockBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-bedrock-platform to use AWS Bedrock platform');
                return BedrockBridge\Factory::createPlatform();

            case 'mistral':
                class_exists(MistralBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-mistral-platform to use Mistral platform');
                return MistralBridge\Factory::createPlatform($apiKey, $client);

            case 'ollama':
                class_exists(OllamaBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-ollama-platform to use Ollama platform');
                $hostUrl = $dsn->host ? ($dsn->port ? "http://{$dsn->host}:{$dsn->port}" : "http://{$dsn->host}") : 'http://localhost:11434';
                return OllamaBridge\Factory::createPlatform($hostUrl, httpClient: $client);

            case 'huggingface':
                class_exists(HuggingFaceBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-hugging-face-platform to use HuggingFace platform');
                $provider = $dsn->query['provider'] ?? HuggingFaceBridge\Provider::HF_INFERENCE;
                return HuggingFaceBridge\Factory::createPlatform($apiKey, $provider);

            case 'replicate':
                class_exists(ReplicateBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-replicate-platform to use Replicate platform');
                return ReplicateBridge\Factory::createPlatform($apiKey, $client);

            case 'lmstudio':
                class_exists(LmStudioBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-lm-studio-platform to use LmStudio platform');
                $baseUrl = $dsn->host ? ($dsn->port ? "http://{$dsn->host}:{$dsn->port}" : "http://{$dsn->host}") : 'http://localhost:1234';
                return LmStudioBridge\Factory::createPlatform($baseUrl, $client);

            case 'albert':
                class_exists(AlbertBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-albert-platform to use Albert platform');
                $baseUrl = $dsn->host ?? '';
                return AlbertBridge\Factory::createPlatform($apiKey, $baseUrl, $client);

            case 'cartesia':
                class_exists(CartesiaBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-cartesia-platform to use Cartesia platform');
                $version = $dsn->query['version'] ?? 'v1';
                return CartesiaBridge\Factory::createPlatform($apiKey, $version, $client);

            case 'elevenlabs':
                class_exists(ElevenLabsBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-eleven-labs-platform to use ElevenLabs platform');
                $hostUrl = $dsn->host ? ($dsn->port ? "https://{$dsn->host}:{$dsn->port}" : "https://{$dsn->host}") : 'https://api.elevenlabs.io/v1';
                return ElevenLabsBridge\Factory::createPlatform($apiKey, $hostUrl, $client);

            case 'perplexity':
                class_exists(PerplexityBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-perplexity-platform to use Perplexity platform');
                return PerplexityBridge\Factory::createPlatform($apiKey, $client);

            case 'scaleway':
                class_exists(ScalewayBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-scaleway-platform to use Scaleway platform');
                return ScalewayBridge\Factory::createPlatform($apiKey, $client);

            case 'voyage':
                class_exists(VoyageBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-voyage-platform to use Voyage platform');
                return VoyageBridge\Factory::createPlatform($apiKey, $client);

            case 'deepseek':
                class_exists(DeepSeekBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-deep-seek-platform to use DeepSeek platform');
                return DeepSeekBridge\Factory::createPlatform($apiKey, $client);

            case 'cerebras':
                class_exists(CerebrasBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-cerebras-platform to use Cerebras platform');
                return CerebrasBridge\Factory::createPlatform($apiKey, $client);

            case 'decart':
                class_exists(DecartBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-decart-platform to use Decart platform');
                $hostUrl = $dsn->host ? ($dsn->port ? "https://{$dsn->host}:{$dsn->port}" : "https://{$dsn->host}") : 'https://api.decart.ai/v1';
                return DecartBridge\Factory::createPlatform($apiKey, $hostUrl, $client);

            case 'aimlapi':
                class_exists(AiMlApiBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-ai-ml-api-platform to use AiMlApi platform');
                $baseUrl = $dsn->host ? ($dsn->port ? "https://{$dsn->host}:{$dsn->port}" : "https://{$dsn->host}") : 'https://api.aimlapi.com';
                return AiMlApiBridge\Factory::createPlatform($apiKey, $client, baseUrl: $baseUrl);

            case 'docker':
                class_exists(DockerModelRunnerBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-docker-model-runner-platform to use Docker ModelRunner platform');
                $hostUrl = $dsn->host ? ($dsn->port ? "http://{$dsn->host}:{$dsn->port}" : "http://{$dsn->host}") : 'http://localhost:12434';
                return DockerModelRunnerBridge\Factory::createPlatform($hostUrl, $client);

            case 'transformers':
                class_exists(TransformersPhpBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-transformers-php-platform to use TransformersPHP platform');
                return TransformersPhpBridge\Factory::createPlatform();

            case 'generic':
                class_exists(GenericBridge\Factory::class) or throw new \RuntimeException('Please install symfony/ai-generic-platform to use Generic platform');
                $baseUrl = $dsn->host ? ($dsn->port ? "https://{$dsn->host}:{$dsn->port}" : "https://{$dsn->host}") : '';
                $genericApiKey = $dsn->query['api_key'] ?? $apiKey;
                return GenericBridge\Factory::createPlatform($baseUrl, $genericApiKey, $client);

            case 'azure-openai':
                class_exists(AzureBridge\OpenAi\Factory::class) or throw new \RuntimeException('Please install symfony/ai-azure-platform to use Azure OpenAI platform');
                $baseUrl = $dsn->host ? ($dsn->port ? "https://{$dsn->host}:{$dsn->port}" : "https://{$dsn->host}") : '';
                $deployment = $dsn->query['deployment'] ?? '';
                $apiVersion = $dsn->query['api_version'] ?? '2023-12-01-preview';
                return AzureBridge\OpenAi\Factory::createPlatform($baseUrl, $deployment, $apiVersion, $apiKey, $client);

            case 'azure-meta':
                class_exists(AzureBridge\Meta\Factory::class) or throw new \RuntimeException('Please install symfony/ai-azure-platform to use Azure Meta platform');
                $baseUrl = $dsn->host ? ($dsn->port ? "https://{$dsn->host}:{$dsn->port}" : "https://{$dsn->host}") : '';
                return AzureBridge\Meta\Factory::createPlatform($baseUrl, $apiKey, $client);

            default:
                throw new \InvalidArgumentException("Unsupported DSN scheme: {$dsn->scheme}");
        }
    }
}
