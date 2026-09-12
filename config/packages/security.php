<?php

declare(strict_types=1);

use App\Entity\User;
use App\Enum\RolesEnum;
use App\Enum\UserRoleEnum;
use App\Security\LoginCodeAuthenticator;
use App\Security\UserChecker;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('security', [
        // No password_hashers: authentication is by one-time code, so no
        // password is ever stored, reset, or leaked.
        'providers' => [
            'app_user_provider' => [
                'entity' => [
                    'class' => User::class,
                    'property' => 'email',
                ],
            ],
        ],

        'firewalls' => [
            'dev' => [
                'pattern' => '^/(_(profiler|wdt)|css|images|js)/',
                'security' => false,
            ],
            'main' => [
                'lazy' => true,
                'provider' => 'app_user_provider',
                'user_checker' => UserChecker::class,
                'stateless' => false,

                'custom_authenticators' => [
                    LoginCodeAuthenticator::class,
                ],
                'entry_point' => LoginCodeAuthenticator::class,

                'logout' => [
                    'path' => 'app_logout',
                ],

                // No remember_me, deliberately. Every signed-in page requires
                // IS_AUTHENTICATED_FULLY and a remember-me cookie only grants
                // IS_AUTHENTICATED_REMEMBERED, so the cookie could not sign
                // anyone in; it could only produce a visitor who looked signed
                // in to app.user while reaching nothing.

                'login_throttling' => [
                    'max_attempts' => 5,
                ],
            ],
        ],

        // The site is public by default: the calendar is the product and a
        // login in front of it defeats the point. Only the pages that write
        // something are gated, and those are gated in their controllers with
        // IsGranted. The rules here exist for the admin namespace, so a page
        // added to it later is not public by omission.
        'access_control' => [
            [
                'path' => '^/admin',
                'roles' => UserRoleEnum::ROLE_ADMIN->name,
            ],
            [
                'path' => '^/',
                'roles' => RolesEnum::PUBLIC_ACCESS->name,
            ],
        ],
    ]);
};
