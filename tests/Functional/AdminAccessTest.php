<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\EventStatusEnum;
use Symfony\Component\HttpFoundation\Response;

/**
 * The back-office is ours, and the queue actually approves things.
 */
final class AdminAccessTest extends WebTestCaseWithTransaction
{
    public function testASignedOutVisitorIsSentToLogin(): void
    {
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/login');
    }

    public function testAnOrdinaryUserIsRefused(): void
    {
        $this->client->loginUser($this->user());
        $this->client->request('GET', '/admin');

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testAnAdminSeesTheDashboardAndTheQueue(): void
    {
        $this->event('Queued Festival', approved: false);

        $this->client->loginUser($this->user(roles: ['ROLE_ADMIN']));

        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->request('GET', '/admin/event');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Queued Festival', $crawler->filter('body')->text());
    }

    public function testApprovingFromTheQueuePublishesTheEvent(): void
    {
        $event = $this->event('To Approve', approved: false);

        $this->client->loginUser($this->user(roles: ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', '/admin/event');

        $link = $crawler->filter('a')->reduce(
            static fn ($node): bool => str_contains((string) $node->attr('href'), '/approve') && str_contains((string) $node->attr('href'), $event->getId()->toRfc4122()),
        );
        self::assertGreaterThan(0, $link->count(), 'No approve link for the pending event.');

        $this->client->request('GET', (string) $link->first()->attr('href'));
        self::assertResponseRedirects();

        $event = $this->em->find(\App\Entity\Event::class, $event->getId());
        self::assertInstanceOf(\App\Entity\Event::class, $event);
        self::assertSame(EventStatusEnum::APPROVED, $event->getStatus());

        // Now public.
        $this->client->request('GET', '/events/' . $event->getSlug());
        self::assertResponseIsSuccessful();
    }

    public function testTheAdminLinkShowsOnlyForAdmins(): void
    {
        $this->client->loginUser($this->user(roles: ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', '/');
        self::assertCount(1, $crawler->filter('header a[href="/admin"]'));

        $this->client->loginUser($this->user());
        $crawler = $this->client->request('GET', '/');
        self::assertCount(0, $crawler->filter('header a[href="/admin"]'));
    }
}
