# EXT:seal_ai

AI Vector search integration for EXT:seal based on symfony/ai.

## Installation

1. Install and configure the [EXT:index](https://github.com/lochmueller/index) & [EXT:seal](https://github.com/lochmueller/seal) extension.
2. Run `composer require lochmueller/seal-ai`
3. Set the search adapter via site configuration to `ai://`
4. Configure your AI Platform via `AI Platform DSN` and your AI vector store via `Ai Store DSN`
5. Install the needed packages from symfony/ai for platform and store. Check the composer.json for possible packages.

## Configuration

Example DSN for platform and store are configured as placeholder in the site configuration module.
