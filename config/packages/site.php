<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Identity of the site and of whoever operates it. Single source of truth: the
 * footer, the legal pages, the emails and the structured data all read this.
 */
return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()->set('app.site', [
        'name' => 'Urban Kizomba Europe',
        'domain' => 'urbankizomba.eu',
        'tagline' => 'Urban kiz festivals, workshops and socials across Europe',
        'email' => 'hello@urbankizomba.eu',
        // Who is legally responsible for the site. Named on the privacy and
        // terms pages, which cannot be published without a responsible party.
        'operator' => 'Kerrial Newham',
        'operator_country' => 'CZ',
        'instagram' => 'https://www.instagram.com/urbankizomba.eu',
    ]);

    // How many days a recurring social can go without someone confirming it is
    // still running before the page says so. Long enough that a summer break
    // does not flag every social in August; short enough that a dead social is
    // not advertised for a year.
    $containerConfigurator->parameters()->set('app.social_confirmation_days', 90);
};
