<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Enum\Locale;
use App\Service\Content\Article;
use App\Service\Content\ArticleLibrary;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SitemapController extends AbstractController
{
    /**
     * The pages a search engine should know about: everything a visitor can read
     * without an account.
     *
     * Listed rather than discovered. Walking the route collection would pick up
     * every route the app has and the interesting failure mode is the wrong
     * direction — a dashboard or an onboarding step quietly published because
     * somebody added a route, not a marketing page missing from a file anyone
     * can read.
     */
    private const array PUBLIC_ROUTES = [
        'app_home',
        'app_calculator',
        'app_pricing',
        'app_terms',
        'app_privacy',
    ];

    /**
     * Declared for the default language alone, which is the one whose prefix is
     * empty — so this is /sitemap.xml and there is no /en/sitemap.xml competing
     * with it. A sitemap is one file listing every language, not one per
     * language.
     */
    #[Route(path: [
        'cs' => '/sitemap.xml',
    ], name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(UrlGeneratorInterface $urlGenerator, ArticleLibrary $articles): Response
    {
        $pages = [];

        foreach (self::PUBLIC_ROUTES as $route) {
            $pages[] = $this->page($urlGenerator, $route);
        }

        // The article index and one entry per article, both restricted to the
        // languages the texts actually exist in. The routes are published in
        // every language, so without that restriction the sitemap would hand a
        // crawler an English index listing nothing and English article URLs that
        // 404.
        //
        // Read from the directory rather than added to the constant above:
        // writing an article should not also mean editing a controller for it to
        // be found.
        $published = $articles->publishedLocales();

        $pages[] = $this->page(
            $urlGenerator,
            'app_articles',
            locales: $published,
            lastModified: $articles->lastUpdated(Locale::DEFAULT->value),
        );

        foreach ($published as $locale) {
            foreach ($articles->all($locale) as $article) {
                $pages[] = $this->page(
                    $urlGenerator,
                    'app_article',
                    [
                        'slug' => $article->slug,
                    ],
                    $article->alternates,
                    $article->updated,
                );
            }
        }

        // An article published in two languages is one page with two alternates,
        // and the loop above reaches it once per language.
        $pages = array_values(array_unique($pages, SORT_REGULAR));

        $pages = array_values(array_filter(
            $pages,
            static fn (?array $page): bool => $page !== null,
        ));

        $response = $this->render('sitemap.xml.twig', [
            'pages' => $pages,
        ]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }

    /**
     * One sitemap entry: the page in every language it is actually published in.
     *
     * A route can be declared in one language alone — the articles are Czech and
     * {@see ArticleController} says why — and asking the generator for a language
     * it has no path in throws. Skipping that language is the whole point: a
     * sitemap listing an English URL that 404s is worse than one that does not
     * mention English at all.
     *
     * Null when the route is published in no language we serve, which means it
     * belongs in no sitemap.
     *
     * @param array<string, string> $parameters
     * @param list<string>|null     $locales    languages this page's content
     *                                          exists in; null means every
     *                                          language the route generates
     *
     * @return array{alternates: array<string, string>, x_default: string, lastmod: ?string}|null
     */
    private function page(
        UrlGeneratorInterface $urlGenerator,
        string $route,
        array $parameters = [],
        ?array $locales = null,
        ?DateTimeImmutable $lastModified = null,
    ): ?array {
        $alternates = [];

        foreach (Locale::active() as $locale) {
            if ($locales !== null && ! in_array($locale->value, $locales, true)) {
                continue;
            }

            try {
                $alternates[$locale->value] = $urlGenerator->generate(
                    $route,
                    [
                        ...$parameters,
                        '_locale' => $locale->value,
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            } catch (RoutingException) {
                continue;
            }
        }

        if ($alternates === []) {
            return null;
        }

        return [
            'alternates' => $alternates,
            // Same choice the hreflang tags make: a visitor who has expressed no
            // language preference is served Czech — or, for a page not published
            // in Czech, whichever language it does exist in.
            'x_default' => $alternates[Locale::DEFAULT->value] ?? reset($alternates),
            // Only where it is a real date the author maintains. Everything else
            // here would have to invent one, and an invented lastmod is worse
            // than none.
            'lastmod' => $lastModified?->format('Y-m-d'),
        ];
    }
}
