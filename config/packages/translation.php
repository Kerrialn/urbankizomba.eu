<?php

declare(strict_types=1);

use App\Enum\Locale;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'default_locale' => Locale::DEFAULT->value,
        // A visitor who has chosen nothing gets Czech: the market is Czech
        // practices, and negotiating from Accept-Language would make the same
        // URL answer differently per visitor. English is reached by asking for
        // it — the /en routes and the switcher in the navbar.
        'enabled_locales' => Locale::activeValues(),
        'translator' => [
            'default_path' => '%kernel.project_dir%/translations',
            'fallbacks' => [
                'cs',
                'en',
            ],
            'providers' => null,
        ],
    ]);
};
