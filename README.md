# EXT:seal_ai

AI Vector search integration for EXT:seal based on symfony/ai.

## Installation

1. Run `composer require lochmueller/seal-ai`
2. Install and configure EXT:seal
3. Configure the search adapter via site configuration to `ai://`

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
> This extension is a proof-of-concept! Please handle the result carfully.
