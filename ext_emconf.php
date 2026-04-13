<?php

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'SEAL Search AI',
    'description' => 'SEAL Search AI - Vector based search based on EXT:seal',
    'version' => '1.0.0',
    'category' => 'fe',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
            'seal' => '1.0.0-1.99.99',
            'php' => '8.3.0-8.99.99',
        ],
    ],
    'state' => 'stable',
    'author' => 'Tim Lochmüller',
    'author_email' => 'tim@fruit-lab.de',
    'author_company' => 'HDNET GmbH & Co. KG',
    'autoload' => [
        'psr-4' => [
            'Lochmueller\\SealAi\\' => 'Classes',
        ],
    ],
];
