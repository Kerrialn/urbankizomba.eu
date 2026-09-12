<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Enum\Locale;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
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
     * The static pages a search engine should know about. Listed rather than
     * discovered: walking the route collection would pick up the account and
     * admin pages, and the interesting failure is a private page published by
     * accident, not a public one missing from a list anyone can read.
     */
    private const array PUBLIC_ROUTES = [
        'app_home',
        'app_events',
        'app_socials',
        'app_cities',
        'app_about',
        'app_newsletter',
        'app_terms',
        'app_privacy',
    ];

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(
        UrlGeneratorInterface $urlGenerator,
        EventRepository $eventRepository,
        CityRepository $cityRepository,
        ArticleLibrary $articles,
    ): Response {
        $today = new DateTimeImmutable('today');
        $pages = [];

        foreach (self::PUBLIC_ROUTES as $route) {
            $pages[] = $this->page($urlGenerator, $route);
        }

        // Every city with something on it, and every upcoming event. Past
        // events are left out: the page still resolves, but it is not
        // something anyone should be sent to.
        foreach ($cityRepository->findActiveWithCounts($today) as $row) {
            $pages[] = $this->page($urlGenerator, 'app_city', [
                'slug' => $row['city']->getSlug(),
            ]);
        }

        foreach ($eventRepository->upcomingQuery($today)->getQuery()->getResult() as $event) {
            $pages[] = $this->page($urlGenerator, 'app_event', [
                'slug' => $event->getSlug(),
            ], $event->getUpdatedAt());
        }

        $published = $articles->publishedLocales();

        if ($published !== []) {
            $pages[] = $this->page($urlGenerator, 'app_articles', [], $articles->lastUpdated(Locale::DEFAULT->value), $published);

            foreach ($published as $locale) {
                foreach ($articles->all($locale) as $article) {
                    $pages[] = $this->page($urlGenerator, 'app_article', [
                        'slug' => $article->slug,
                    ], $article->updated, $article->alternates);
                }
            }
        }

        $pages = array_values(array_filter(
            array_unique($pages, SORT_REGULAR),
            static fn (?array $page): bool => $page !== null,
        ));

        $response = $this->render('sitemap.xml.twig', [
            'pages' => $pages,
        ]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }

    /**
     * One sitemap entry: the page in every language it is published in.
     *
     * @param array<string, string> $parameters
     * @param list<string>|null     $locales
     *
     * @return array{alternates: array<string, string>, x_default: string, lastmod: ?string}|null
     */
    private function page(
        UrlGeneratorInterface $urlGenerator,
        string $route,
        array $parameters = [],
        ?DateTimeImmutable $lastModified = null,
        ?array $locales = null,
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
            'x_default' => $alternates[Locale::DEFAULT->value] ?? reset($alternates),
            'lastmod' => $lastModified?->format('Y-m-d'),
        ];
    }
}
