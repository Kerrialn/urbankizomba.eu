<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The languages the site publishes in.
 *
 *   - {@see self::cases()}  — every language the app has cases for.
 *   - {@see self::active()} — the subset currently offered (routing, hreflang,
 *                             language switcher, translation catalogues).
 *
 * English is the only active language for now. The routing, hreflang and
 * sitemap code is written for several, so activating another is a matter of
 * flipping isActive(), giving every route a path for it, and writing the
 * catalogue. A language with no `translations/*.<code>.php` renders as the
 * fallback locale rather than failing, which is easy to ship without noticing.
 */
enum Locale: string
{
    case En = 'en';
    case Fr = 'fr';
    case De = 'de';
    case Pt = 'pt';

    /**
     * The unprefixed language. Matches framework.default_locale.
     */
    public const DEFAULT = self::En;

    /**
     * URL prefix for this language — empty for the default one.
     */
    public function routePrefix(): string
    {
        return $this === self::DEFAULT ? '' : '/' . $this->value;
    }

    /**
     * Active languages as locale code => route prefix, for routes.php.
     *
     * @return array<string, string>
     */
    public static function routePrefixes(): array
    {
        $prefixes = [];

        foreach (self::active() as $locale) {
            $prefixes[$locale->value] = $locale->routePrefix();
        }

        return $prefixes;
    }

    public function isActive(): bool
    {
        return match ($this) {
            self::En => true,
            self::Fr, self::De, self::Pt => false,
        };
    }

    public function nativeName(): string
    {
        return match ($this) {
            self::En => 'English',
            self::Fr => 'Français',
            self::De => 'Deutsch',
            self::Pt => 'Português',
        };
    }

    /**
     * The language tag Open Graph wants: language_TERRITORY, not the bare code.
     */
    public function openGraphLocale(): string
    {
        return match ($this) {
            self::En => 'en_GB',
            self::Fr => 'fr_FR',
            self::De => 'de_DE',
            self::Pt => 'pt_PT',
        };
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $locale): bool => $locale->isActive()));
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_map(static fn (self $locale): string => $locale->value, self::active());
    }
}
