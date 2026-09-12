<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The single source of truth for the languages the platform publishes in.
 *
 *   - {@see self::cases()}  — every language the app has cases for.
 *   - {@see self::active()} — the subset currently offered (routing, hreflang,
 *                             language switcher, translation catalogues).
 *
 * Backed by the two-letter code Symfony uses as the request "_locale", so it
 * bridges to routing and templates via `->value` / `Locale::from()`.
 *
 * Activating a language is not a one-line change here: routes.php builds a URL
 * prefix per active language, so every route needs a path for it, and a language
 * with no `translations/*.<code>.php` catalogue renders as the fallback locale
 * rather than failing, which is easy to ship without noticing.
 */
enum Locale: string
{
    case Cs = 'cs';
    case En = 'en';
    case De = 'de';
    case It = 'it';
    case Sk = 'sk';
    case Ru = 'ru';
    case Uk = 'uk';

    /**
     * The unprefixed language: Czech URLs stay canonical (/cenik), every other
     * active language is served under its own prefix (/en/pricing). Matches
     * framework.default_locale.
     */
    public const DEFAULT = self::Cs;

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

    /**
     * Whether the language is currently offered to visitors.
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::Cs, self::En => true,
            self::De, self::It, self::Sk, self::Ru, self::Uk => false,
        };
    }

    /**
     * English display name (e.g. for admin choice fields).
     */
    public function label(): string
    {
        return match ($this) {
            self::En => 'English',
            self::Cs => 'Czech',
            self::Sk => 'Slovak',
            self::Ru => 'Russian',
            self::Uk => 'Ukrainian',
            self::De => 'German',
            self::It => 'Italian',
        };
    }

    /**
     * Endonym — the language's name in its own language (for the switcher UI).
     */
    public function nativeName(): string
    {
        return match ($this) {
            self::En => 'English',
            self::Cs => 'Čeština',
            self::Sk => 'Slovenčina',
            self::Ru => 'Русский',
            self::Uk => 'Українська',
            self::De => 'Deutsch',
            self::It => 'Italiano',
        };
    }

    /**
     * The language tag Open Graph wants: language_TERRITORY, not the bare code.
     *
     * Facebook and LinkedIn silently ignore an og:locale they cannot parse, so
     * "cs" alone buys nothing. The territory is the one the language is being
     * served to rather than the one it originates in — this is a Czech product
     * and its English is written for the same audience.
     */
    public function openGraphLocale(): string
    {
        return match ($this) {
            self::Cs => 'cs_CZ',
            self::En => 'en_GB',
            self::Sk => 'sk_SK',
            self::De => 'de_DE',
            self::It => 'it_IT',
            self::Ru => 'ru_RU',
            self::Uk => 'uk_UA',
        };
    }

    /**
     * Active languages.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $locale): bool => $locale->isActive()));
    }

    /**
     * Codes of every known language.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $locale): string => $locale->value, self::cases());
    }

    /**
     * Codes of the active languages.
     *
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_map(static fn (self $locale): string => $locale->value, self::active());
    }

    /**
     * Resolve a (possibly null/unknown) code to a known language, defaulting when
     * it isn't one we serve.
     */
    public static function coerce(?string $value): self
    {
        return ($value !== null ? self::tryFrom(strtolower($value)) : null) ?? self::DEFAULT;
    }
}
