<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Identity of the site and of whoever operates it. Single source of truth: the
 * footer, the legal pages, the emails and the structured data all read this.
 */
return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()->set('app.site', [
        'name' => 'Urban Kizomba',
        'domain' => 'urbankizomba.eu',
        'tagline' => 'Urban kiz festivals, workshops and socials across Europe',
        'email' => 'hello@urbankizomba.eu',
        // Who is legally responsible for the site. Named on the privacy and
        // terms pages, which cannot be published without a responsible party.
        'operator' => 'Kerrial Newham',
        'operator_country' => 'CZ',
        'instagram' => 'https://www.instagram.com/urbankizomba.eu',
    ]);

    // The landing-page hero: a muted, looping clip of people dancing, so a
    // newcomer sees what urban kiz is before reading a word. Paths are under
    // public/. When the video file is missing the hero shows the poster alone,
    // so the page never breaks on a fresh checkout.
    //
    // Footage belongs to whoever filmed it. Only use a clip with the owner's
    // permission, and name them: credit_name and credit_url are printed on
    // the hero. Keep the file small (H.264 MP4, no audio track, ~10 seconds,
    // under 8 MB) — it is downloaded by every visitor on every visit.
    $containerConfigurator->parameters()->set('app.hero', [
        // Set to null to show the poster still alone, keeping the file in place.
        'video' => null,
        // 'video' => 'video/hero.mp4',
        'poster' => 'images/hero-poster.jpg',
        'credit_name' => null,
        'credit_url' => null,
    ]);

    // How many days a recurring social can go without someone confirming it is
    // still running before the page says so. Long enough that a summer break
    // does not flag every social in August; short enough that a dead social is
    // not advertised for a year.
    $containerConfigurator->parameters()->set('app.social_confirmation_days', 90);
};
