<?php

declare(strict_types=1);

$lll = 'LLL:EXT:seal_ai/Resources/Private/Language/locallang.xlf:';

$GLOBALS['SiteConfiguration']['site']['columns']['sealAiPlatformDsn'] = [
    'label' => $lll . 'site.sealAiPlatformDsn',
    'description' => $lll . 'site.sealAiPlatformDsn.description',
    'config' => [
        'type' => 'input',
        'eval' => 'trim',
        'default' => '',
        'placeholder' =>  'openrouter://APIKEY_HERE@openrouter.ai?model=gemini-embedding-001&dimensions=768',
    ],
];

$GLOBALS['SiteConfiguration']['site']['columns']['sealAiChatModel'] = [
    'label' => $lll . 'site.sealAiChatModel',
    'description' => $lll . 'site.sealAiChatModel.description',
    'config' => [
        'type' => 'input',
        'eval' => 'trim',
        'default' => '',
        'placeholder' => 'google/gemini-2.0-flash',
    ],
];

$GLOBALS['SiteConfiguration']['site']['columns']['sealAiStoreDsn'] = [
    'label' => $lll . 'site.sealAiStoreDsn',
    'description' => $lll . 'site.sealAiStoreDsn.description',
    'config' => [
        'type' => 'input',
        'eval' => 'trim',
        'default' => '',
        'placeholder' =>  'mariadb://localhost?dimensions=768',
    ],
];

$GLOBALS['SiteConfiguration']['site']['columns']['sealAiEmbeddingCache'] = [
    'label' => $lll . 'site.sealAiEmbeddingCache',
    'description' => $lll . 'site.sealAiEmbeddingCache.description',
    'config' => [
        'type' => 'check',
        'renderType' => 'checkboxToggle',
        'default' => 1,
    ],
];

$GLOBALS['SiteConfiguration']['site']['columns']['sealAiMcpToken'] = [
    'label' => $lll . 'site.sealAiMcpToken',
    'description' => $lll . 'site.sealAiMcpToken.description',
    'config' => [
        'type' => 'password',
        'hashed' => false,
        'default' => '',
        'fieldControl' => [
            'passwordGenerator' => [
                'renderType' => 'passwordGenerator',
                'options' => [
                    'title' => 'Generate MCP token',
                    'passwordRules' => [
                        'length' => 40,
                        'random' => 'base64',
                    ],
                ],
            ],
        ],
    ],
];

$GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'] = str_replace(
    ', sealSearchDsn,',
    ', sealSearchDsn, sealAiPlatformDsn, sealAiChatModel, sealAiStoreDsn, sealAiEmbeddingCache, sealAiMcpToken, ',
    $GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'],
);
