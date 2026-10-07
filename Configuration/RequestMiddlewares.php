<?php

declare(strict_types=1);

use Lochmueller\SealAi\Middleware\McpMiddleware;

return [
    'frontend' => [
        'seal-ai/mcp' => [
            'target' => McpMiddleware::class,
            'before' => [
                'typo3/cms-frontend/backend-user-authentication',
            ],
            'after' => [
                'typo3/cms-frontend/site',
            ],
        ],
    ],
];
