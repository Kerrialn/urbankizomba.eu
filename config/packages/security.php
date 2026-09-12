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

                // No remember_me, deliberately. Every controller in the app
                // requires IS_AUTHENTICATED_FULLY and a remember-me cookie only
                // ever grants IS_AUTHENTICATED_REMEMBERED, so the cookie could
                // not sign anyone in — it could only produce a visitor who looked
                // signed in to `app.user` and `getUser()` while reaching nothing.
                // That state caused three separate bugs (a login redirect loop, a
                // navbar offering links that bounced, and registration turning
                // away someone who could not get in) and saved the email round
                // trip it was added for in none of them. If monthly logins are
                // worth shortening later, the answer is to decide which pages
                // accept REMEMBERED — not to reissue a cookie nothing honours.

                'login_throttling' => [
                    'max_attempts' => 5,
                ],
            ],
        ],

        // Patterns match the URL, not the route name, so each one has to cover
        // every language the route is published in: /cenik and /en/pricing are
        // the same page. A rule that only names the Czech path stops applying
        // the moment a visitor switches language.
        'access_control' => [
            [
                'path' => '^(/en)?/login',
                'roles' => RolesEnum::PUBLIC_ACCESS->name,
            ],
            [
                'path' => '^(/en)?/register',
                'roles' => RolesEnum::PUBLIC_ACCESS->name,
            ],
            [
                'path' => '^(/cenik|/en/pricing)',
                'roles' => RolesEnum::PUBLIC_ACCESS->name,
            ],
            [
                // The bonifikace calculator. It is the page the advertising
                // points at and it collects nothing, so requiring a login would
                // defeat the only thing it is for.
                'path' => '^(/kalkulacka|/en/calculator)',
                'roles' => RolesEnum::PUBLIC_ACCESS->name,
            ],
            [
                // The articles are what a practice finds in a search engine
                // before it has heard of us; a login in front of the answer ends
                // the visit. Czech paths only, because that is where they are
                // published — see ArticleController.
                'path' => '^/clanky(/|$)',
                'roles' => RolesEnum::PUBLIC_ACCESS->name,
            ],
            [
                // A sitemap behind a login is a sitemap nothing can read. Named
                // in robots.txt, so it is fetched by a crawler with no session.
                'path' => '^/sitemap\.xml$',
                'roles' => RolesEnum::PUBLIC_ACCESS->name,
            ],
            [
                // Read before signing up, not after. A visitor cannot be asked to
                // agree to terms they must authenticate to read.
                'path' => '^(/obchodni-podminky|/en/terms|/ochrana-osobnich-udaju|/en/privacy)',
                'roles' => RolesEnum::PUBLIC_ACCESS->name,
            ],
            [
                // Stripe posts here with no session. The webhook signature is
                // what authenticates it, checked in the controller against the
                // raw body — the firewall has nothing to check.
                'path' => '^/webhook/stripe$',
                'roles' => RolesEnum::PUBLIC_ACCESS->name,
            ],
            [
                'path' => '^/easy-admin',
                'roles' => UserRoleEnum::ROLE_ADMIN->name,
            ],
            [
                // Ours. Covers every language the prefix publishes it under, and
                // covers the whole namespace rather than the one route in it, so
                // an admin page added later is not public by omission.
                'path' => '^(/en)?/admin',
                'roles' => UserRoleEnum::ROLE_ADMIN->name,
            ],
        ],
    ]);
};
