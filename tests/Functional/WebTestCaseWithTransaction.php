<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\City;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EventTypeEnum;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A browser and a database, with every test rolled back in tearDown so tests
 * never see each other's rows. Builders for the three entities most tests
 * need; each takes only what varies and picks a sensible default for the rest.
 */
abstract class WebTestCaseWithTransaction extends WebTestCase
{
    protected KernelBrowser $client;

    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();

        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    /**
     * Unique per run. The login-code limiter is keyed by address and stored
     * in a cache pool that no database rollback touches.
     */
    protected function uniqueEmail(string $prefix): string
    {
        return sprintf('%s-%s@example.com', $prefix, bin2hex(random_bytes(6)));
    }

    /**
     * @param list<string> $roles
     */
    protected function user(?string $email = null, array $roles = []): User
    {
        $user = new User($email ?? $this->uniqueEmail('user'));
        $user->setRoles($roles);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function city(string $name = 'Testville', string $country = 'DE'): City
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . bin2hex(random_bytes(3));
        $city = new City($name, $country, $slug);
        $this->em->persist($city);
        $this->em->flush();

        return $city;
    }

    protected function event(
        string $title = 'Test Festival',
        ?City $city = null,
        EventTypeEnum $type = EventTypeEnum::FESTIVAL,
        bool $approved = true,
        ?DateTimeImmutable $startsAt = null,
        ?User $submittedBy = null,
    ): Event {
        $city ??= $this->city();
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $title)) . '-' . bin2hex(random_bytes(3));

        $event = new Event($title, $type, $city, $slug);
        $event->setDescription('A description long enough to pass validation for ' . $title . '.');
        $event->setSubmittedBy($submittedBy);

        if ($type->isRecurring()) {
            $event->setSchedule('Every Thursday, 21:00');
        } else {
            $event->setStartsAt($startsAt ?? new DateTimeImmutable('+10 days'));
        }

        if ($approved) {
            $event->approve();
        }

        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }
}
