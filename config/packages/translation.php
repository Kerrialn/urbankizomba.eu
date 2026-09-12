<?php

declare(strict_types=1);

use App\Enum\Locale;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'default_locale' => Locale::DEFAULT->value,
        // English only. The scene is international and English is the language
        // the festivals themselves publish in, so one language covers every
        // country without halving the time available for content.
        'enabled_locales' => Locale::activeValues(),
        'translator' => [
            'default_path' => '%kernel.project_dir%/translations',
            'fallbacks' => [
                'en',
            ],
            'providers' => null,
        ],
    ]);
};
