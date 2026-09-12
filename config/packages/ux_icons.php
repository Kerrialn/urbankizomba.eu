<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('ux_icons', [
        'icon_dir' => '%kernel.project_dir%/assets/icons',
        'default_icon_attributes' => [
            'fill' => 'currentColor',
            'font-size' => '1.25em',
        ],
        'iconify' => [
            'enabled' => true,
            'on_demand' => true,
            'endpoint' => 'https://api.iconify.design',
        ],
        'ignore_not_found' => false,
    ]);
};
