<?php

declare(strict_types=1);

use App\Enum\Locale;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routingConfigurator): void {
    $routingConfigurator->import('@AutocompleteBundle/config/routes.php');

    // Controllers are grouped by area (Controller/, and later Api/, Seo/, …).
    // Imported recursively because every area currently shares the same routing.
    // Split this back into per-area imports the moment one area needs its own
    // prefix.
    //
    // Every route is generated once per active language: Czech keeps the bare
    // path it has always had (/cenik) and every other language is prefixed
    // (/en/pricing), so the Czech URLs already in circulation stay canonical.
    // Routes whose path reads the same in both languages need no attribute
    // change — they are duplicated under the prefix automatically. The
    // trailing-slash argument is what keeps the home page at /en rather than
    // /en/.
    $routingConfigurator->import(
        resource: __DIR__ . '/../src/Controller/',
        type: 'attribute',
    )->prefix(Locale::routePrefixes(), trailingSlashOnRoot: false);
};
