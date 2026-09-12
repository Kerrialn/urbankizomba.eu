<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'rate_limiter' => [
            // Codes cost real money once SMS is switched on, and an unlimited
            // "send me a code" button is a way to spam a third party's inbox.
            'login_code_request' => [
                'policy' => 'sliding_window',
                'limit' => 5,
                'interval' => '15 minutes',
            ],
        ],
    ]);
};
