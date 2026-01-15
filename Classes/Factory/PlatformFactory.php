<?php

declare(strict_types=1);

namespace Lochmueller\SealAi\Factory;

use Lochmueller\SealAi\Dto\DsnDto;
use Symfony\AI\Platform\Bridge\HuggingFace\Provider;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Bridge\OpenRouter\PlatformFactory as OpenRouterPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory as AnthropicPlatformFactory;
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory as GeminiPlatformFactory;
use Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory as VertexAiPlatformFactory;
use Symfony\AI\Platform\Bridge\Mistral\PlatformFactory as MistralPlatformFactory;
use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory as OllamaPlatformFactory;
use Symfony\AI\Platform\Bridge\HuggingFace\PlatformFactory as HuggingFacePlatformFactory;
use Symfony\AI\Platform\Bridge\Replicate\PlatformFactory as ReplicatePlatformFactory;
use Symfony\AI\Platform\Bridge\LmStudio\PlatformFactory as LmStudioPlatformFactory;
use Symfony\AI\Platform\Bridge\Albert\PlatformFactory as AlbertPlatformFactory;
use Symfony\AI\Platform\Bridge\Cartesia\PlatformFactory as CartesiaPlatformFactory;
use Symfony\AI\Platform\Bridge\ElevenLabs\PlatformFactory as ElevenLabsPlatformFactory;
use Symfony\AI\Platform\Bridge\Perplexity\PlatformFactory as PerplexityPlatformFactory;
use Symfony\AI\Platform\Bridge\Scaleway\PlatformFactory as ScalewayPlatformFactory;
use Symfony\AI\Platform\Bridge\Voyage\PlatformFactory as VoyagePlatformFactory;
use Symfony\AI\Platform\Bridge\DeepSeek\PlatformFactory as DeepSeekPlatformFactory;
use Symfony\AI\Platform\Bridge\Cerebras\PlatformFactory as CerebrasPlatformFactory;
use Symfony\AI\Platform\Bridge\Decart\PlatformFactory as DecartPlatformFactory;
use Symfony\AI\Platform\Bridge\AiMlApi\PlatformFactory as AiMlApiPlatformFactory;
use Symfony\AI\Platform\Bridge\DockerModelRunner\PlatformFactory as DockerModelRunnerPlatformFactory;
use Symfony\AI\Platform\Bridge\TransformersPhp\PlatformFactory as TransformersPhpPlatformFactory;
use Symfony\AI\Platform\Bridge\Generic\PlatformFactory as GenericPlatformFactory;
use Symfony\AI\Platform\Bridge\Azure\OpenAi\PlatformFactory as AzureOpenAiPlatformFactory;
use Symfony\AI\Platform\Bridge\Azure\Meta\PlatformFactory as AzureMetaPlatformFactory;
use Symfony\Component\HttpClient\HttpClient;

class PlatformFactory
{
    public function fromDsn(DsnDto $dsn): PlatformInterface
    {
        $apiKey = $dsn->user;
        $client = HttpClient::create();

        // DSN Examples:
        // openai://api-key@default
        // anthropic://api-key@default
        // gemini://api-key@default
        // openrouter://api-key@default
        // vertex://location/project-id?api_key=api-key
        // bedrock://
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
            case 'openai':
                if (!class_exists(OpenAiPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-open-ai-platform to use OpenAI platform');
                }
                return OpenAiPlatformFactory::create($apiKey, $client);

            case 'anthropic':
                if (!class_exists(AnthropicPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-anthropic-platform to use Anthropic platform');
                }
                return AnthropicPlatformFactory::create($apiKey, $client);

            case 'gemini':
                if (!class_exists(GeminiPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-gemini-platform to use Gemini platform');
                }
                return GeminiPlatformFactory::create($apiKey, $client);

            case 'openrouter':
                if (!class_exists(OpenRouterPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-open-router-platform to use OpenRouter platform');
                }
                return OpenRouterPlatformFactory::create($apiKey, $client);

            case 'vertex':
            case 'vertexai':
                if (!class_exists(VertexAiPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-vertex-ai-platform to use VertexAI platform');
                }
                $location = $dsn->host ?? $dsn->query['location'] ?? '';
                $projectId = $dsn->query['project_id'] ?? '';
                $vertexApiKey = $dsn->query['api_key'] ?? $apiKey;
                return VertexAiPlatformFactory::create($location, $projectId, $vertexApiKey, $client);

            case 'mistral':
                if (!class_exists(MistralPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-mistral-platform to use Mistral platform');
                }
                return MistralPlatformFactory::create($apiKey, $client);

            case 'ollama':
                if (!class_exists(OllamaPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-ollama-platform to use Ollama platform');
                }
                $hostUrl = $dsn->host ? ($dsn->port ? "http://{$dsn->host}:{$dsn->port}" : "http://{$dsn->host}") : 'http://localhost:11434';
                return OllamaPlatformFactory::create($hostUrl, $client);

            case 'huggingface':
                if (!class_exists(HuggingFacePlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-hugging-face-platform to use HuggingFace platform');
                }
                $provider = $dsn->query['provider'] ?? Provider::HF_INFERENCE;
                return HuggingFacePlatformFactory::create($apiKey, $provider);

            case 'replicate':
                if (!class_exists(ReplicatePlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-replicate-platform to use Replicate platform');
                }
                return ReplicatePlatformFactory::create($apiKey, $client);

            case 'lmstudio':
                if (!class_exists(LmStudioPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-lm-studio-platform to use LmStudio platform');
                }
                $baseUrl = $dsn->host ? ($dsn->port ? "http://{$dsn->host}:{$dsn->port}" : "http://{$dsn->host}") : 'http://localhost:1234';
                return LmStudioPlatformFactory::create($baseUrl, $client);

            case 'albert':
                if (!class_exists(AlbertPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-albert-platform to use Albert platform');
                }
                $baseUrl = $dsn->host ?? '';
                return AlbertPlatformFactory::create($apiKey, $baseUrl, $client);

            case 'cartesia':
                if (!class_exists(CartesiaPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-cartesia-platform to use Cartesia platform');
                }
                $version = $dsn->query['version'] ?? 'v1';
                return CartesiaPlatformFactory::create($apiKey, $version, $client);

            case 'elevenlabs':
                if (!class_exists(ElevenLabsPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-eleven-labs-platform to use ElevenLabs platform');
                }
                $hostUrl = $dsn->host ? ($dsn->port ? "https://{$dsn->host}:{$dsn->port}" : "https://{$dsn->host}") : 'https://api.elevenlabs.io/v1';
                return ElevenLabsPlatformFactory::create($apiKey, $hostUrl, $client);

            case 'perplexity':
                if (!class_exists(PerplexityPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-perplexity-platform to use Perplexity platform');
                }
                return PerplexityPlatformFactory::create($apiKey, $client);

            case 'scaleway':
                if (!class_exists(ScalewayPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-scaleway-platform to use Scaleway platform');
                }
                return ScalewayPlatformFactory::create($apiKey, $client);

            case 'voyage':
                if (!class_exists(VoyagePlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-voyage-platform to use Voyage platform');
                }
                return VoyagePlatformFactory::create($apiKey, $client);

            case 'deepseek':
                if (!class_exists(DeepSeekPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-deep-seek-platform to use DeepSeek platform');
                }
                return DeepSeekPlatformFactory::create($apiKey, $client);

            case 'cerebras':
                if (!class_exists(CerebrasPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-cerebras-platform to use Cerebras platform');
                }
                return CerebrasPlatformFactory::create($apiKey, $client);

            case 'decart':
                if (!class_exists(DecartPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-decart-platform to use Decart platform');
                }
                $hostUrl = $dsn->host ? ($dsn->port ? "https://{$dsn->host}:{$dsn->port}" : "https://{$dsn->host}") : 'https://api.decart.ai/v1';
                return DecartPlatformFactory::create($apiKey, $hostUrl, $client);

            case 'aimlapi':
                if (!class_exists(AiMlApiPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-ai-ml-api-platform to use AiMlApi platform');
                }
                $baseUrl = $dsn->host ? ($dsn->port ? "https://{$dsn->host}:{$dsn->port}" : "https://{$dsn->host}") : 'https://api.aimlapi.com';
                return AiMlApiPlatformFactory::create($apiKey, $client, baseUrl: $baseUrl);

            case 'docker':
                if (!class_exists(DockerModelRunnerPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-docker-model-runner-platform to use Docker ModelRunner platform');
                }
                $hostUrl = $dsn->host ? ($dsn->port ? "http://{$dsn->host}:{$dsn->port}" : "http://{$dsn->host}") : 'http://localhost:12434';
                return DockerModelRunnerPlatformFactory::create($hostUrl, $client);

            case 'transformers':
                if (!class_exists(TransformersPhpPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-transformers-php-platform to use TransformersPHP platform');
                }
                return TransformersPhpPlatformFactory::create();

            case 'generic':
                if (!class_exists(GenericPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-generic-platform to use Generic platform');
                }
                $baseUrl = $dsn->host ? ($dsn->port ? "https://{$dsn->host}:{$dsn->port}" : "https://{$dsn->host}") : '';
                $genericApiKey = $dsn->query['api_key'] ?? $apiKey;
                return GenericPlatformFactory::create($baseUrl, $genericApiKey, $client);

            case 'azure-openai':
                if (!class_exists(AzureOpenAiPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-azure-platform to use Azure OpenAI platform');
                }
                $baseUrl = $dsn->host ? ($dsn->port ? "https://{$dsn->host}:{$dsn->port}" : "https://{$dsn->host}") : '';
                $deployment = $dsn->query['deployment'] ?? '';
                $apiVersion = $dsn->query['api_version'] ?? '2023-12-01-preview';
                return AzureOpenAiPlatformFactory::create($baseUrl, $deployment, $apiVersion, $apiKey, $client);

            case 'azure-meta':
                if (!class_exists(AzureMetaPlatformFactory::class)) {
                    throw new \RuntimeException('Please install symfony/ai-azure-platform to use Azure Meta platform');
                }
                $baseUrl = $dsn->host ? ($dsn->port ? "https://{$dsn->host}:{$dsn->port}" : "https://{$dsn->host}") : '';
                return AzureMetaPlatformFactory::create($baseUrl, $apiKey, $client);

            default:
                throw new \InvalidArgumentException("Unsupported DSN scheme: {$dsn->scheme}");
        }
    }
}
