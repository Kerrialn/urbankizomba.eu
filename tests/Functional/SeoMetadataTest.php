<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\EventTypeEnum;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The tags that decide how a page looks in a search result and in a pasted
 * link. None of it is visible on the page, which is why it needs pinning.
 */
final class SeoMetadataTest extends WebTestCaseWithTransaction
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function publicPages(): iterable
    {
        yield 'home' => ['/'];
        yield 'events' => ['/events'];
        yield 'socials' => ['/socials'];
        yield 'cities' => ['/cities'];
        yield 'about' => ['/about'];
        yield 'newsletter' => ['/newsletter'];
        yield 'guides' => ['/guides'];
        yield 'guide' => ['/guides/what-is-urban-kiz'];
        yield 'terms' => ['/terms'];
        yield 'privacy' => ['/privacy'];
    }

    /**
     * @dataProvider publicPages
     */
    public function testCarriesTheSocialCardTags(string $url): void
    {
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        foreach (['og:title', 'og:description', 'og:url', 'og:image', 'og:type'] as $property) {
            $tag = $crawler->filter(sprintf('meta[property="%s"]', $property));

            self::assertCount(1, $tag, sprintf('%s is missing from %s.', $property, $url));
            self::assertNotSame('', trim((string) $tag->attr('content')));
        }

        self::assertCount(1, $crawler->filter('link[rel="canonical"]'));
        self::assertStringStartsWith('http', (string) $crawler->filter('link[rel="alternate"]')->first()->attr('href'));

        $image = (string) $crawler->filter('meta[property="og:image"]')->attr('content');
        self::assertFileExists(
            self::getContainer()->getParameter('kernel.project_dir') . '/public' . parse_url($image, PHP_URL_PATH),
        );
    }

    public function testTheCanonicalDropsFiltersAndPageNumbers(): void
    {
        $crawler = $this->client->request('GET', '/events?country=DE&page=2');

        $canonical = (string) $crawler->filter('link[rel="canonical"]')->attr('href');

        self::assertStringNotContainsString('?', $canonical);
        self::assertStringEndsWith('/events', $canonical);
    }

    public function testTheSitemapListsPublicPagesAndApprovedContentOnly(): void
    {
        $city = $this->city('Sitemap City', 'NL');
        $approved = $this->event('Sitemap Festival', $city);
        $pending = $this->event('Hidden Festival', $city, approved: false);
        $this->event('Sitemap Social', $city, EventTypeEnum::SOCIAL);

        $this->client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');

        $xml = (string) $this->client->getResponse()->getContent();

        foreach (['/', '/events', '/socials', '/cities', '/guides/what-is-urban-kiz', '/cities/' . $city->getSlug(), '/events/' . $approved->getSlug()] as $path) {
            self::assertStringContainsString(sprintf('<loc>http://localhost%s</loc>', $path), $xml);
        }

        self::assertStringNotContainsString($pending->getSlug(), $xml);

        foreach (['/account', '/submit', '/admin', '/login'] as $private) {
            self::assertStringNotContainsString('<loc>http://localhost' . $private, $xml);
        }
    }

    public function testEverySitemapUrlResolves(): void
    {
        $this->event('Resolving Festival');

        $this->client->request('GET', '/sitemap.xml');
        preg_match_all('#<loc>([^<]+)</loc>#', (string) $this->client->getResponse()->getContent(), $matches);

        self::assertNotEmpty($matches[1]);

        foreach ($matches[1] as $url) {
            $path = (string) parse_url(html_entity_decode($url), PHP_URL_PATH);
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful(sprintf('%s is in the sitemap but does not resolve.', $path));
        }
    }

    public function testRobotsNamesTheSitemapAndKeepsThePrivatePagesOut(): void
    {
        $robots = (string) file_get_contents(
            self::getContainer()->getParameter('kernel.project_dir') . '/public/robots.txt',
        );

        self::assertStringContainsString('Sitemap: https://urbankizomba.eu/sitemap.xml', $robots);

        foreach (['/account', '/submit', '/admin'] as $private) {
            self::assertStringContainsString('Disallow: ' . $private, $robots);
        }
    }

    /**
     * @dataProvider publicPages
     */
    public function testNoTranslationKeyLeaksOntoThePage(string $url): void
    {
        $this->client->request('GET', $url);

        // Scripts are dropped first: the Turnstile bootstrap says
        // "window.turnstile.render", which is JavaScript, not a leaked key.
        $html = (string) preg_replace('#<script\b[^>]*>.*?</script>#is', '', (string) $this->client->getResponse()->getContent());
        $text = (new Crawler($html))->filter('body')->text();

        // A leaked key looks like "home.upcoming.heading": lower-case words
        // joined by dots with no spaces. Real copy never does.
        self::assertDoesNotMatchRegularExpression(
            '/(?<![\w\/.@-])[a-z]+(?:\.[a-z_]+){2,}(?![\w\/.-])/',
            $text,
            sprintf('An untranslated key reached %s.', $url),
        );
    }

    /**
     * @dataProvider publicPages
     */
    public function testEveryAlternateResolves(string $url): void
    {
        $crawler = $this->client->request('GET', $url);

        $hrefs = $crawler->filter('link[rel="alternate"]')->each(
            static fn (Crawler $link): string => (string) $link->attr('href'),
        );

        self::assertNotEmpty($hrefs, sprintf('%s declares no hreflang alternates.', $url));

        foreach (array_unique($hrefs) as $href) {
            $path = (string) parse_url($href, PHP_URL_PATH);
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful(sprintf('%s declares an alternate at %s, which does not resolve.', $url, $path));
        }
    }
}
