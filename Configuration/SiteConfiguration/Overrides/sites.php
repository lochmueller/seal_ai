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

$GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'] = str_replace(
    ', sealSearchDsn,',
    ', sealSearchDsn, sealAiPlatformDsn, sealAiStoreDsn, ',
    $GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'],
);
