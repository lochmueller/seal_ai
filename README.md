# EXT:seal_ai

AI Vector search integration for [EXT:seal](https://github.com/lochmueller/seal) based on [symfony/ai](https://symfony.com/ai).

Enables semantic (vector-based) search in TYPO3 by bridging the SEAL search abstraction with the Symfony AI platform and store components. Documents are automatically vectorized via configurable embedding models and stored in a vector database of your choice.

This extension was funded by the [TYPO3 Association](https://typo3.org): [community ideas I](https://typo3.org/article/members-have-selected-five-ideas-to-be-funded-in-quarter-3-2025) / [community idea II](https://talk.typo3.org/t/ext-seal-ecosystem-expansion-advanced-search-ai-vector-integration-tim-lochmuller/6594) & [first blogpost](https://typo3.org/article/typo3-meets-seal-a-breath-of-fresh-air-for-search) / [second blogpost](https://news.typo3.com/article/extseal-takes-the-next-step-advanced-search-geo-features-ai-vector-integration)

## Requirements

- PHP 8.3+
- TYPO3 13.4 or 14.2
- [EXT:index](https://github.com/lochmueller/index) & [EXT:seal](https://github.com/lochmueller/seal) installed and configured

## Installation

```bash
composer require lochmueller/seal-ai
```

Additionally, install the symfony/ai packages for your chosen platform and store (see [Supported Platforms](#supported-platforms) and [Supported Stores](#supported-stores)).

## Configuration

1. Set the SEAL search adapter to `ai://` in your site configuration.
2. Configure the two DSN fields added to the TYPO3 site configuration module:
   - **AI Platform DSN** (`sealAiPlatformDsn`) — connection to the embedding/AI provider
   - **AI Store DSN** (`sealAiStoreDsn`) — connection to the vector store

The platform DSN **must** include a `model` query parameter specifying the embedding model:

```
openai://YOUR_API_KEY@default?model=text-embedding-3-small
```

### DSN Format

```
scheme://user@host:port?param1=value1&param2=value2
```

- `scheme` — provider identifier (e.g. `openai`, `qdrant`)
- `user` — API key (where applicable)
- `host` / `port` — server address
- Query parameters — provider-specific options

## Supported Platforms

Each platform requires its own composer package. Install only what you need.

| Scheme         | Package                                   | Example DSN                                                 |
|----------------|-------------------------------------------|-------------------------------------------------------------|
| `openai`       | `symfony/ai-open-ai-platform`             | `openai://api-key@default?model=text-embedding-3-small`     |
| `anthropic`    | `symfony/ai-anthropic-platform`           | `anthropic://api-key@default`                               |
| `gemini`       | `symfony/ai-gemini-platform`              | `gemini://api-key@default`                                  |
| `openrouter`   | `symfony/ai-open-router-platform`         | `openrouter://api-key@default?model=gemini-embedding-001`   |
| `vertex`       | `symfony/ai-vertex-ai-platform`           | `vertex://location/project-id?api_key=key`                  |
| `bedrock`      | `symfony/ai-bedrock-platform`             | `bedrock://default`                                         |
| `mistral`      | `symfony/ai-mistral-platform`             | `mistral://api-key@default`                                 |
| `ollama`       | `symfony/ai-ollama-platform`              | `ollama://localhost:11434`                                  |
| `huggingface`  | `symfony/ai-hugging-face-platform`        | `huggingface://api-key@default?provider=hf_inference`       |
| `replicate`    | `symfony/ai-replicate-platform`           | `replicate://api-key@default`                               |
| `lmstudio`     | `symfony/ai-lm-studio-platform`           | `lmstudio://localhost:1234`                                 |
| `deepseek`     | `symfony/ai-deep-seek-platform`           | `deepseek://api-key@default`                                |
| `voyage`       | `symfony/ai-voyage-platform`              | `voyage://api-key@default`                                  |
| `albert`       | `symfony/ai-albert-platform`              | `albert://api-key@host`                                     |
| `cartesia`     | `symfony/ai-cartesia-platform`            | `cartesia://api-key@default?version=v1`                     |
| `elevenlabs`   | `symfony/ai-eleven-labs-platform`         | `elevenlabs://api-key@host`                                 |
| `perplexity`   | `symfony/ai-perplexity-platform`          | `perplexity://api-key@default`                              |
| `scaleway`     | `symfony/ai-scaleway-platform`            | `scaleway://api-key@default`                                |
| `cerebras`     | `symfony/ai-cerebras-platform`            | `cerebras://api-key@default`                                |
| `edenai`       | `symfony/ai-eden-ai-platform`             | `edenai://api-key@default?model=openai/text-embedding-3-small` |
| `decart`       | `symfony/ai-decart-platform`              | `decart://api-key@host`                                     |
| `aimlapi`      | `symfony/ai-ai-ml-api-platform`           | `aimlapi://api-key@host`                                    |
| `docker`       | `symfony/ai-docker-model-runner-platform` | `docker://localhost:12434`                                  |
| `transformers` | `symfony/ai-transformers-php-platform`    | `transformers://`                                           |
| `generic`      | `symfony/ai-generic-platform`             | `generic://host?api_key=key`                                |
| `azure-openai` | `symfony/ai-azure-platform`               | `azure-openai://api-key@host?deployment=dep&api_version=ver` |
| `azure-meta`   | `symfony/ai-azure-platform`               | `azure-meta://api-key@host`                                 |
| `openresponses`| `symfony/ai-open-responses-platform`      | `openresponses://api-key@host?path=/v1/responses`           |
| `failover`     | `symfony/ai-failover-platform`            | `failover://default?platforms[]=...&platforms[]=...&model=...` |

### Failover platform

The `failover` scheme wraps several platforms, which are tried in the given order until one succeeds.
Each fallback is a nested platform DSN in the `platforms[]` query parameter. URL-encode nested DSNs that
contain their own `?` / `&`. The `model` parameter belongs to the outer DSN and is used for all platforms.

```
failover://default?platforms[]=openai://sk-key@default&platforms[]=ollama://localhost:11434&model=text-embedding-3-small
```

> All platforms have to provide the same embedding model (or at least one with identical dimensions),
> otherwise the stored vectors are not comparable.

### AiM integration (EXT:aim)

If [b13/aim](https://github.com/b13/aim) is installed (`composer require b13/aim`), embeddings and search summaries
(SGE) can be routed through the AiM proxy instead of a directly configured platform. Requests then go through the AiM
middleware pipeline (request logging, cost tracking, budgets, fallback) and providers / API keys are managed in the
AiM backend module.

```
aim://default
aim://default?model=openai:text-embedding-3-small&dimensions=768
```

- `model` — AiM provider notation for the embedding requests (optional, defaults to the AiM default embedding provider)
- `dimensions` — requested vector dimensions (optional)

With an `aim://` platform DSN, `sealAiChatModel` is an AiM provider notation as well (e.g. `anthropic:claude-sonnet-4`).
Use `default` to use the AiM default conversation provider. Leave it empty to disable SGE.

AiM does not provide a vector store, so `sealAiStoreDsn` has to be configured as usual.

> Pin a concrete embedding model (`provider:model`). If AiM reroutes embedding requests to another model,
> the vector dimensions may change and the stored vectors are no longer comparable.

### MCP server (mcp/sdk)

If [mcp/sdk](https://github.com/modelcontextprotocol/php-sdk) is installed (`composer require mcp/sdk`), every site can
provide a read-only [MCP](https://modelcontextprotocol.io) server, so AI agents (Claude, ChatGPT, Cursor, ...) can
use the website search. The server uses the SEAL engine of the site, so it works with every SEAL adapter (not only `ai://`).

1. Set a token in the site configuration (**MCP Server Token**, `sealAiMcpToken`). Without a token the server is disabled.
   Use `%env(SEAL_AI_MCP_TOKEN)%` to keep the token out of the `config.yaml`.
2. Connect the client to `<site/language base>/seal/mcp` (Streamable HTTP) with the header `Authorization: Bearer <token>`.

```json
{
  "mcpServers": {
    "my-website": {
      "type": "http",
      "url": "https://www.example.com/seal/mcp",
      "headers": { "Authorization": "Bearer YOUR_TOKEN" }
    }
  }
}
```

Tools (all read-only, bound to the site and language of the requested URL):

- `search` — `query`, optional `limit` (max. 50) and `offset`. Returns ID, title, URI, a short snippet and the score.
- `retrieve` — `id` from a search result. Returns the full document (title, URI, content, tags, ...).

> Vector stores provide no lookup by ID. Therefore documents of search results are cached for 24h
> (cache `seal_ai_mcp`) and `retrieve` works for documents that were returned by a previous `search`.

## Supported Stores

| Scheme          | Package                             | Example DSN                                                             |
|-----------------|-------------------------------------|-------------------------------------------------------------------------|
| `mariadb`       | `symfony/ai-maria-db-store`         | `mariadb://default?tableName=my_table`                                  |
| `postgres`      | `symfony/ai-postgres-store`         | `postgres://default?tableName=my_table`                                 |
| `qdrant`        | `symfony/ai-qdrant-store`           | `qdrant://api-key@host:6333?collectionName=col`                         |
| `elasticsearch` | `symfony/ai-elasticsearch-store`    | `elasticsearch://host:9200?indexName=idx`                               |
| `opensearch`    | `symfony/ai-open-search-store`      | `opensearch://host:9200?indexName=idx`                                  |
| `meilisearch`   | `symfony/ai-meilisearch-store`      | `meilisearch://api-key@host:7700?indexName=idx`                         |
| `milvus`        | `symfony/ai-milvus-store`           | `milvus://api-key@host:19530?database=db&collection=col`                |
| `redis`         | `symfony/ai-redis-store`            | `redis://host:6379?indexName=idx`                                       |
| `weaviate`      | `symfony/ai-weaviate-store`         | `weaviate://api-key@host:8080?collection=col`                           |
| `typesense`     | `symfony/ai-typesense-store`        | `typesense://api-key@host:8108?collection=col`                          |
| `neo4j`         | `symfony/ai-neo4j-store`            | `neo4j://user:pass@host:7474?databaseName=neo4j&vectorIndexName=idx`    |
| `cloudflare`    | `symfony/ai-cloudflare-store`       | `cloudflare://api-key@default?accountId=acc&index=idx`                  |
| `mongodb`       | `symfony/ai-mongo-db-store`         | `mongodb://host:27017?databaseName=db&collectionName=col&indexName=idx` |
| `surrealdb`     | `symfony/ai-surreal-db-store`       | `surrealdb://user:pass@host:8000?namespace=ns&database=db`              |
| `manticore`     | `symfony/ai-manticore-search-store` | `manticore://host:9308?table=tbl`                                       |
| `clickhouse`    | `symfony/ai-click-house-store`      | `clickhouse://host:8123?databaseName=db&tableName=tbl`                  |
| `vektor`        | `symfony/ai-vektor-store`           | `vektor://default/path?dimensions=1536`                                 |
| `s3vectors`     | `symfony/ai-s3vectors-store`        | `s3vectors://region@default?vectorBucketName=bucket&indexName=idx`      |
| `chroma`        | `symfony/ai-chroma-db-store`        | `chroma://api-key@host:8000?collectionName=col&tenant=t&database=db`    |
| `cache`         | `symfony/ai-cache-store`            | `cache://default/path?key=my_key&strategy=cosine`                       |

> For `mariadb` and `postgres` stores, the extension reuses the existing TYPO3 database connection automatically.

> The `cache` store keeps all vectors in a filesystem cache pool and is meant for small indexes,
> local development and testing. It defaults to `var/cache/data/seal-ai`.

## Extending via Events

Both factories support the `event://` DSN scheme, allowing you to provide custom platform or store implementations via PSR-14 event listeners.

- `Lochmueller\SealAi\Event\PlatformFactoryEvent` — dispatched for `event://` platform DSN
- `Lochmueller\SealAi\Event\StoreFactoryEvent` — dispatched for `event://` store DSN

Example event listener:

```php
use Lochmueller\SealAi\Event\PlatformFactoryEvent;

final class CustomPlatformListener
{
    public function __invoke(PlatformFactoryEvent $event): void
    {
        $dsn = $event->getDsn();
        // Build your custom platform...
        $event->setPlatform($myCustomPlatform);
    }
}
```

## Architecture

![Structure overview](Documentation/Structure.png)

The extension implements the `CmsIg\Seal\Adapter\AdapterInterface` with these components:

- **AiAdapterFactory** — registered as SEAL adapter factory for the `ai://` scheme, initializes the bridge from the current site configuration
- **AiBridge** — central service that holds the initialized platform, store, and vectorizer
- **AiSchemaManager** — manages index creation/deletion in the vector store
- **AiIndexer** — vectorizes documents and stores them; handles save, delete, and bulk operations
- **AiSearcher** — performs semantic search by vectorizing the query and querying the store

## Development

```bash
# Install dependencies
composer install

# Fix code style (PHP-CS-Fixer, PER-CS 3.0)
composer code-fix

# Static analysis (PHPStan level 8)
composer code-check

# Run unit tests
composer code-test

# Tests with coverage (requires Xdebug)
composer code-test-coverage
```

### Comparing available symfony/ai packages

```bash
diff --color \
  <(grep -E 'symfony\/ai-[a-z0-9-]+-(store|platform)' composer.json) \
  <(curl -s https://raw.githubusercontent.com/symfony/ai-bundle/refs/heads/main/composer.json \
  | sed -nE '/"symfony\/ai-[a-z0-9-]+-(store|platform)":/s/^    //p')
```

### Updating available symfony/ai packages

```bash
jq --argjson updates "$(curl -sL https://raw.githubusercontent.com/symfony/ai-bundle/main/composer.json | jq '."require-dev" | with_entries(select(.key | test("^symfony/ai-.*-(store|platform)$")))')" '."require-dev" |= ((. // {}) + $updates | to_entries | sort_by(.key) | from_entries)' composer.json | sponge composer.json
```
