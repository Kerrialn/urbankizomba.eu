<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'rate_limiter' => [
            // An unlimited "send me a code" button is a way to spam a third
            // party's inbox from our domain.
            'login_code_request' => [
                'policy' => 'sliding_window',
                'limit' => 5,
                'interval' => '15 minutes',
            ],
            // Same reasoning for the newsletter: the confirmation email goes to
            // whatever address was typed.
            'newsletter_signup' => [
                'policy' => 'sliding_window',
                'limit' => 5,
                'interval' => '15 minutes',
            ],
        ],
    ]);
};
