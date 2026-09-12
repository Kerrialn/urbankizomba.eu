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
        // Only the client IP and the scheme. The dev Caddy image forwards
        // X-Forwarded-Port as "0" and X-Forwarded-Host without its port, and
        // Symfony trusts either over the Host header: every generated URL came
        // out as https://localhost:0, which also failed the stateless CSRF
        // origin check on every form. The Host header is right in both dev
        // (localhost:8443) and prod (Traefik passes it through).
        'trusted_headers' => ['x-forwarded-for', 'x-forwarded-proto'],
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
