<?php

declare(strict_types=1);

use B13\Aim\Ai;
use Mcp\Server;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    // Optional EXT:aim integration (composer suggest: b13/aim)
    if (class_exists(Ai::class)) {
        $services->load('Lochmueller\\SealAi\\Integration\\Aim\\', '../Classes/Integration/Aim/*');
    }

    // Optional MCP server (composer suggest: mcp/sdk)
    if (class_exists(Server::class)) {
        $services->load('Lochmueller\\SealAi\\Integration\\Mcp\\', '../Classes/Integration/Mcp/*');
    }
};
