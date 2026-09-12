<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\EventTypeEnum;
use DateTimeImmutable;

/**
 * The calendar as a visitor sees it: what is listed, what is not, and where.
 */
final class PublicPagesTest extends WebTestCaseWithTransaction
{
    public function testAnApprovedEventIsOnTheListAndHasAPage(): void
    {
        $event = $this->event('Approved Weekend');

        $crawler = $this->client->request('GET', '/events');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Approved Weekend', $crawler->filter('main')->text());

        $this->client->request('GET', '/events/' . $event->getSlug());
        self::assertResponseIsSuccessful();
    }

    public function testAPendingEventIsNotListedAndIs404ForAStranger(): void
    {
        $event = $this->event('Secret Pending Festival', approved: false);

        $crawler = $this->client->request('GET', '/events');
        self::assertStringNotContainsString('Secret Pending Festival', $crawler->filter('main')->text());

        $this->client->request('GET', '/events/' . $event->getSlug());
        self::assertResponseStatusCodeSame(404);
    }

    public function testAPendingEventIsVisibleToItsSubmitter(): void
    {
        $user = $this->user();
        $event = $this->event('My Pending Festival', approved: false, submittedBy: $user);

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/events/' . $event->getSlug());

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('meta[name="robots"][content="noindex"]'));
    }

    public function testAPastEventDropsOffTheListButKeepsItsPage(): void
    {
        $event = $this->event('Last Year Festival', startsAt: new DateTimeImmutable('-30 days'));

        $crawler = $this->client->request('GET', '/events');
        self::assertStringNotContainsString('Last Year Festival', $crawler->filter('main')->text());

        $this->client->request('GET', '/events/' . $event->getSlug());
        self::assertResponseIsSuccessful();
    }

    public function testAMultiDayEventIsStillListedOnItsSecondDay(): void
    {
        $event = $this->event('Running Now Festival', startsAt: new DateTimeImmutable('-1 day'));
        $event->setEndsAt(new DateTimeImmutable('+1 day'));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/events');
        self::assertStringContainsString('Running Now Festival', $crawler->filter('main')->text());
    }

    public function testTheCountryFilterNarrowsTheList(): void
    {
        $this->event('German Festival', $this->city('Berlin', 'DE'));
        $this->event('French Festival', $this->city('Paris', 'FR'));

        $crawler = $this->client->request('GET', '/events?country=FR');
        $text = $crawler->filter('main')->text();

        self::assertStringContainsString('French Festival', $text);
        self::assertStringNotContainsString('German Festival', $text);
        self::assertCount(1, $crawler->filter('meta[name="robots"]'), 'A filtered list must not be indexed.');
    }

    public function testSocialsAreOnTheSocialsPageAndNotOnTheEventsList(): void
    {
        $this->event('Thursday Kiz', type: EventTypeEnum::SOCIAL);

        $crawler = $this->client->request('GET', '/socials');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Thursday Kiz', $crawler->filter('main')->text());

        $crawler = $this->client->request('GET', '/events');
        self::assertStringNotContainsString('Thursday Kiz', $crawler->filter('main')->text());
    }

    public function testACityPageListsItsSocialsAndUpcomingEvents(): void
    {
        $city = $this->city('Lisbon', 'PT');
        $this->event('Lisbon Social', $city, EventTypeEnum::SOCIAL);
        $this->event('Lisbon Weekender', $city, EventTypeEnum::WEEKENDER);

        $crawler = $this->client->request('GET', '/cities/' . $city->getSlug());
        self::assertResponseIsSuccessful();

        $text = $crawler->filter('main')->text();
        self::assertStringContainsString('Lisbon Social', $text);
        self::assertStringContainsString('Lisbon Weekender', $text);

        $crawler = $this->client->request('GET', '/cities');
        self::assertStringContainsString('Lisbon', $crawler->filter('main')->text());
    }

    public function testACityWithNothingApprovedHasNoPage(): void
    {
        $city = $this->city('Ghost Town', 'DE');
        $this->event('Unreviewed', $city, approved: false);

        $this->client->request('GET', '/cities/' . $city->getSlug());
        self::assertResponseStatusCodeSame(404);

        $crawler = $this->client->request('GET', '/cities');
        self::assertStringNotContainsString('Ghost Town', $crawler->filter('main')->text());
    }

    public function testTheEventPageCarriesStructuredData(): void
    {
        $event = $this->event('Structured Festival');

        $crawler = $this->client->request('GET', '/events/' . $event->getSlug());
        $script = $crawler->filter('script[type="application/ld+json"]');

        self::assertCount(1, $script);

        $data = json_decode($script->text(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('DanceEvent', $data['@type']);
        self::assertSame('Structured Festival', $data['name']);
        self::assertSame($event->getStartsAt()?->format('Y-m-d'), $data['startDate']);
    }

    /**
     * @dataProvider staticPages
     */
    public function testStaticPagesRender(string $path): void
    {
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function staticPages(): iterable
    {
        yield 'home' => ['/'];
        yield 'about' => ['/about'];
        yield 'newsletter' => ['/newsletter'];
        yield 'guides' => ['/guides'];
        yield 'guide' => ['/guides/what-is-urban-kiz'];
        yield 'terms' => ['/terms'];
        yield 'privacy' => ['/privacy'];
    }

    public function testSubmitRequiresSignIn(): void
    {
        $this->client->request('GET', '/submit');
        self::assertResponseRedirects('/login');
    }
}
