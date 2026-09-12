<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'router' => [
            'default_uri' => '%env(APP_URL)%',
        ],
        'secret' => '%env(APP_SECRET)%',
        'session' => true,
        'trusted_proxies' => '%env(TRUSTED_PROXIES)%',
        'trusted_headers' => ['x-forwarded-for', 'x-forwarded-proto', 'x-forwarded-port'],
        'http_client' => [
            'default_options' => [
                'timeout' => 10,
            ],
        ],
    ]);
    if ($containerConfigurator->env() === 'test') {
        $containerConfigurator->extension('framework', [
            'test' => true,
            'session' => [
                'storage_factory_id' => 'session.storage.factory.mock_file',
            ],
        ]);
    }
};
