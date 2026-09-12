<?php

declare(strict_types=1);

use App\Enum\Locale;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routingConfigurator): void {
    // Every route is generated once per active language. With English as the
    // only active language that is one unprefixed copy, but the machinery is
    // kept: activating a second language in Locale::isActive() publishes every
    // route under its prefix with no routing change.
    $routingConfigurator->import(
        resource: __DIR__ . '/../src/Controller/',
        type: 'attribute',
    )->prefix(Locale::routePrefixes(), trailingSlashOnRoot: false);
};
