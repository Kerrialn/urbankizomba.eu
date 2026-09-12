<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routingConfigurator): void {
    if ($routingConfigurator->env() === 'dev') {
        $routingConfigurator->import('@PentatrionViteBundle/Resources/config/routing.yaml')
            ->prefix('/build');

        $routingConfigurator->add('_profiler_vite', '/_profiler/vite')
            ->controller('Pentatrion\ViteBundle\Controller\ProfilerController::info');
    }
};
