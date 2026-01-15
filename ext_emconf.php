<?php

declare(strict_types=1);

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'SEAL Search AI',
    'description' => 'SEAL Search AI - Vector based search based on EXT:seal',
    'version' => '0.0.5',
    'category' => 'fe',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'seal' => '0.0.2-0.99.99',
            'php' => '8.3.0-8.99.99',
        ],
    ],
    'state' => 'stable',
    'author' => 'Tim Lochmüller',
    'author_email' => 'tim@fruit-lab.de',
    'author_company' => 'HDNET GmbH & Co. KG',
    'autoload' => [
        'psr-4' => [
            'Lochmueller\\Seal\\' => 'Classes',
        ],
    ],
];
