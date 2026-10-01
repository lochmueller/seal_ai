<?php

declare(strict_types=1);

use B13\Aim\Ai;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    // Optional EXT:aim integration (composer suggest: b13/aim)
    if (!class_exists(Ai::class)) {
        return;
    }

    $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->private()
        ->load('Lochmueller\\SealAi\\Integration\\Aim\\', '../Classes/Integration/Aim/*');
};
