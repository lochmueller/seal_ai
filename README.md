# EXT:seal_ai

AI Vector search integration for EXT:seal based on symfony/ai.

## Installation

1. Install and configure the [EXT:index](https://github.com/lochmueller/index) & [EXT:seal](https://github.com/lochmueller/seal) extension.
2. Run `composer require lochmueller/seal-ai`
   1. There is no official release of symfony/ai. So you have to include `symfony/ai-platform` & `symfony/ai-store` in the same way like here: https://github.com/lochmueller/seal_ai/blob/main/composer.json
4. Set the search adapter via site configuration to `ai://`
5. Configure your AI Platform via `AI Platform DSN` and your AI vector store via `Ai Store DSN`

## Requirements

- You need MariaDB >=11.7 to use this feature.
- You need to configure your TYPO3 installation with PDO and not mysqli
- A Google AI Studio API Key

## Configuration

Example DSN

```ai://localhost/?dimensions=768&api_key=XXXX&tableNameSuffix=_dummy_optional```

@todo fix dependency in extension configuration


> ## ATTENTION
>
> This extension is a proof-of-concept! Please handle the result carefully.
