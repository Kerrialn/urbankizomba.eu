<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    // EasyAdmin's own assets are served from public/bundles/easyadmin, which
    // `assets:install` writes at build time (see the Dockerfile).
    $containerConfigurator->extension('easy_admin', []);
};
