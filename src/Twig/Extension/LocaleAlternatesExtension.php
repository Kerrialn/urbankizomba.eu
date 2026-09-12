<?php

declare(strict_types = 1);

namespace App\Twig\Extension;

use App\Enum\Locale;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The current page's URL in every language it is published in.
 *
 * Feeds both the navbar switcher and the hreflang tags, so a visitor switching
 * language stays on the page they were reading instead of being dropped on the
 * home page — and search engines are told the two URLs are the same document.
 *
 * Routes are localized, which means the name carries a language suffix
 * (app_pricing.cs). Generating the sibling URL therefore starts from
 * "_canonical_route" — the name without the suffix — and asks the generator for
 * the other language.
 */
final class LocaleAlternatesExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('locale_alternates', fn (): array => $this->alternates()),
        ];
    }

    /**
     * @return array<string, array{locale: Locale, url: string, absolute_url: string, current: bool}>
     */
    public function alternates(): array
    {
        $request = $this->requestStack->getMainRequest();

        if (! $request instanceof \Symfony\Component\HttpFoundation\Request) {
            return [];
        }

        $route = $request->attributes->get('_canonical_route')
            ?? $request->attributes->get('_route');

        if (! is_string($route) || $route === '') {
            return [];
        }

        $parameters = $request->attributes->get('_route_params');
        $parameters = is_array($parameters) ? $parameters : [];

        // Both are routing bookkeeping rather than path parameters, and passing
        // them through would append them as a query string.
        unset($parameters['_locale'], $parameters['_canonical_route']);

        $current = $request->getLocale();
        $alternates = [];

        foreach (Locale::active() as $locale) {
            $routeParameters = [
                ...$parameters,
                '_locale' => $locale->value,
            ];

            try {
                $url = $this->urlGenerator->generate($route, $routeParameters);
                // hreflang is only honoured on absolute URLs, so both forms are
                // generated here rather than pasted together in the template.
                $absoluteUrl = $this->urlGenerator->generate(
                    $route,
                    $routeParameters,
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            } catch (RoutingException) {
                // A route not published in this language — a bundle's own route,
                // or an error page rendered outside routing. Offering a link
                // that 404s is worse than offering none.
                continue;
            }

            $alternates[$locale->value] = [
                'locale' => $locale,
                'url' => $url,
                'absolute_url' => $absoluteUrl,
                'current' => $locale->value === $current,
            ];
        }

        return $alternates;
    }
}
