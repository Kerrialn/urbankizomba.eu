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
        // Not x-forwarded-port. The dev Caddy image sends "0" for it, Symfony
        // trusts it over the Host header, and every generated URL came out as
        // https://localhost:0 — which also failed the CSRF origin check on
        // every form. The port comes from X-Forwarded-Host (with its port in
        // dev) or defaults from the scheme (443 behind Traefik in prod).
        'trusted_headers' => ['x-forwarded-for', 'x-forwarded-proto', 'x-forwarded-host'],
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
