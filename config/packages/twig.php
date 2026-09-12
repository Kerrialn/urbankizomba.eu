<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('twig', [
        'file_name_pattern' => '*.twig',
        'form_themes' => [
            '@Autocomplete/form/autocomplete_widget.html.twig',
            'form/custom_theme.html.twig',
            'tailwind_2_layout.html.twig',
            'form/tailwind_dropzone.html.twig',
            'form/switch.html.twig',
            'form/password_input.html.twig',
            'form/phone_number.html.twig',
            'form/pin_code.html.twig',
            'form/money_input.html.twig',
            'form/flatpickr.html.twig',
            'form/date_range.html.twig',
            'form/ux_autocomplete_floating.html.twig',
            'form/turnstile.html.twig',
            'form/selection.html.twig',
            'form/column_checklist.html.twig',
        ],
        'globals' => [
            'stripe_public_key' => '%env(STRIPE_PUBLIC_KEY)%',
            'company' => '%app.company%',
            // The service, not a second env var: the banner has to be driven by
            // the same value SendPatientMessageHandler branches on, or the UI can
            // claim a dry run while messages are really going out.
            'sms_delivery' => '@App\Service\Sms\DeliveryMode',
            'vapid_public_key' => '%env(VAPID_PUBLIC_KEY)%',
        ],
    ]);
    if ($containerConfigurator->env() === 'test') {
        $containerConfigurator->extension('twig', [
            'strict_variables' => true,
        ]);
    }
};
