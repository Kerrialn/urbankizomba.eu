<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('twig', [
        'file_name_pattern' => '*.twig',
        'form_themes' => [
            'form/custom_theme.html.twig',
            'tailwind_2_layout.html.twig',
            'form/pin_code.html.twig',
            'form/turnstile.html.twig',
            'form/selection.html.twig',
        ],
        'globals' => [
            'site' => '%app.site%',
        ],
    ]);
    if ($containerConfigurator->env() === 'test') {
        $containerConfigurator->extension('twig', [
            'strict_variables' => true,
        ]);
    }
};
