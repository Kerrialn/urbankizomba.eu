<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\City;
use App\Entity\Event;
use App\Enum\EventStatusEnum;
use App\Repository\CityRepository;
use App\Repository\EventRepository;

/**
 * Submitting, editing and confirming, as the person who runs the event.
 */
final class EventSubmissionTest extends WebTestCaseWithTransaction
{
    public function testASubmissionLandsInTheQueueWithANewCity(): void
    {
        $user = $this->user();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/submit');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form([
            'event_submission_form[title]' => 'Brand New Weekender',
            'event_submission_form[type]' => 'weekender',
            'event_submission_form[newCityName]' => 'Leipzig',
            'event_submission_form[newCityCountry]' => 'DE',
            'event_submission_form[startsAt]' => (new \DateTimeImmutable('+30 days'))->format('Y-m-d'),
            'event_submission_form[endsAt]' => (new \DateTimeImmutable('+32 days'))->format('Y-m-d'),
            'event_submission_form[description]' => 'Two nights of urban kiz in a city that was not on the list yet.',
            'event_submission_form[organiser]' => 'Leipzig Kiz',
            'event_submission_form[url]' => 'https://example.com/leipzig',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $event = self::getContainer()->get(EventRepository::class)->findOneBy([
            'title' => 'Brand New Weekender',
        ]);
        self::assertInstanceOf(Event::class, $event);
        self::assertSame(EventStatusEnum::PENDING, $event->getStatus());
        self::assertTrue($event->isSubmittedBy($user));
        self::assertSame('brand-new-weekender-leipzig-' . (new \DateTimeImmutable('+30 days'))->format('Y'), $event->getSlug());

        $city = self::getContainer()->get(CityRepository::class)->findOneByNameAndCountry('Leipzig', 'DE');
        self::assertInstanceOf(City::class, $city);
        self::assertSame($city->getId()->toRfc4122(), $event->getCity()->getId()->toRfc4122());
    }

    public function testASocialNeedsAScheduleNotADate(): void
    {
        $this->client->loginUser($this->user());
        $city = $this->city('Berlin', 'DE');

        $crawler = $this->client->request('GET', '/submit');
        $form = $crawler->filter('form')->form([
            'event_submission_form[title]' => 'Thursday Kiz Social',
            'event_submission_form[type]' => 'social',
            'event_submission_form[city]' => $city->getId()->toRfc4122(),
            'event_submission_form[description]' => 'The weekly social with a beginner taster at the start.',
        ]);
        $crawler = $this->client->submit($form);

        // Re-rendered with the error, not redirected.
        self::assertResponseIsUnprocessable();
        self::assertStringContainsString('when the social happens', $crawler->filter('form')->text());

        $form = $crawler->filter('form')->form([
            'event_submission_form[schedule]' => 'Every Thursday, 21:00',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects();

        $event = self::getContainer()->get(EventRepository::class)->findOneBy([
            'title' => 'Thursday Kiz Social',
        ]);
        self::assertInstanceOf(Event::class, $event);
        self::assertTrue($event->isRecurring());
        self::assertNull($event->getStartsAt());
        self::assertSame('Every Thursday, 21:00', $event->getSchedule());
    }

    public function testADatedEventNeedsAStartDate(): void
    {
        $this->client->loginUser($this->user());
        $city = $this->city('Berlin', 'DE');

        $crawler = $this->client->request('GET', '/submit');
        $crawler = $this->client->submit($crawler->filter('form')->form([
            'event_submission_form[title]' => 'Dateless Festival',
            'event_submission_form[type]' => 'festival',
            'event_submission_form[city]' => $city->getId()->toRfc4122(),
            'event_submission_form[description]' => 'A festival that forgot to say when it happens.',
        ]));

        self::assertResponseIsUnprocessable();
        self::assertStringContainsString('first day', $crawler->filter('form')->text());
    }

    public function testEditingAnApprovedEventSendsItBackForReview(): void
    {
        $user = $this->user();
        $event = $this->event('Approved Festival', submittedBy: $user);
        self::assertTrue($event->isApproved());

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/account/events/' . $event->getId() . '/edit');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->filter('form')->form([
            'event_submission_form[title]' => 'Approved Festival, renamed',
        ]));
        self::assertResponseRedirects();

        // The client resets services between requests, which clears the
        // identity map; a fresh find reads what the controller wrote.
        $event = $this->em->find(Event::class, $event->getId());
        self::assertInstanceOf(Event::class, $event);
        self::assertSame('Approved Festival, renamed', $event->getTitle());
        self::assertSame(EventStatusEnum::PENDING, $event->getStatus());
    }

    public function testSomebodyElsesEventCannotBeEdited(): void
    {
        $event = $this->event('Not Yours', submittedBy: $this->user());

        $this->client->loginUser($this->user());
        $this->client->request('GET', '/account/events/' . $event->getId() . '/edit');

        self::assertResponseStatusCodeSame(404);
    }

    public function testConfirmingASocialUpdatesTheDate(): void
    {
        $user = $this->user();
        $social = $this->event('Old Social', type: \App\Enum\EventTypeEnum::SOCIAL, submittedBy: $user);

        $reflection = new \ReflectionProperty(Event::class, 'lastConfirmedAt');
        $reflection->setValue($social, new \DateTimeImmutable('-200 days'));
        $this->em->flush();
        self::assertTrue($social->isStale(90));

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/account');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->filter('form[action$="/confirm"]')->form());
        self::assertResponseRedirects('/account');

        $social = $this->em->find(Event::class, $social->getId());
        self::assertInstanceOf(Event::class, $social);
        self::assertFalse($social->isStale(90));
    }
}
