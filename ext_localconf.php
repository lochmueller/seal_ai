<?php

declare(strict_types=1);

use Lochmueller\SealAi\Integration\Mcp\SearchTools;
use Lochmueller\SealAi\Vectorizer\CachingVectorizer;
use TYPO3\CMS\Core\Cache\Backend\FileBackend;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

defined('TYPO3') or die();

// Documents of MCP search results (vector stores have no lookup by ID)
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][SearchTools::CACHE_IDENTIFIER] ??= [
    'frontend' => VariableFrontend::class,
    'backend' => FileBackend::class,
    'groups' => ['pages'],
];

// Embeddings of indexed content (content-hash cache). Own group, so "flush frontend caches" keeps the paid embeddings.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][CachingVectorizer::CACHE_IDENTIFIER] ??= [
    'frontend' => VariableFrontend::class,
    'backend' => Typo3DatabaseBackend::class,
    'options' => [
        'defaultLifetime' => 2592000,
        'compression' => true,
    ],
    'groups' => ['seal_ai'],
];
