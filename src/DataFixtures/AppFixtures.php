<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\City;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EventTypeEnum;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * A handful of cities and events so a fresh dev database has something on
 * every page. Not seeded in production: real listings come from the form,
 * and the seed cities from app:cities:seed.
 */
final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $admin = new User('admin@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $manager->persist($admin);

        $organiser = new User('organiser@example.com');
        $manager->persist($organiser);

        $cities = [];

        foreach ([
            ['Berlin', 'DE', 'berlin'],
            ['Paris', 'FR', 'paris'],
            ['Lisbon', 'PT', 'lisbon'],
            ['Prague', 'CZ', 'prague'],
            ['Warsaw', 'PL', 'warsaw'],
            ['Milan', 'IT', 'milan'],
        ] as [$name, $country, $slug]) {
            $city = new City($name, $country, $slug);
            $manager->persist($city);
            $cities[$slug] = $city;
        }

        $today = new DateTimeImmutable('today');

        $events = [
            ['Berlin Urban Kiz Weekend', EventTypeEnum::WEEKENDER, 'berlin', 20, 22, 'Kizomba Club Berlin', 'Three nights, two rooms, twelve teachers.', true],
            ['Paris Urban Fusion Festival', EventTypeEnum::FESTIVAL, 'paris', 45, 48, 'Festival Collective', 'The big one: four days of workshops and parties across three venues.', true],
            ['Lisbon Tarraxo Intensive', EventTypeEnum::WORKSHOP, 'lisbon', 12, 13, 'Studio Alfama', 'Two days of tarraxo technique with a party on Saturday night.', true],
            ['Prague Kiz Night', EventTypeEnum::PARTY, 'prague', 5, null, 'Kiz Praha', 'One night, one room, guest DJ from Warsaw.', true],
            ['Milan Urban Kiz Weekender', EventTypeEnum::WEEKENDER, 'milan', 70, 72, 'Milano Kiz', 'Late-autumn weekender in a converted warehouse.', true],
            ['Warsaw Winter Kiz', EventTypeEnum::FESTIVAL, 'warsaw', 100, 103, 'Warsaw Kiz Family', 'Pending review: the reviewer has not looked at this one yet.', false],
        ];

        foreach ($events as [$title, $type, $citySlug, $startOffset, $endOffset, $organiserName, $description, $approved]) {
            $startsAt = $today->modify(sprintf('+%d days', $startOffset));
            $event = new Event($title, $type, $cities[$citySlug], $this->slug($title) . '-' . $startsAt->format('Y'));
            $event->setStartsAt($startsAt);
            $event->setEndsAt($endOffset === null ? null : $today->modify(sprintf('+%d days', $endOffset)));
            $event->setOrganiser($organiserName);
            $event->setDescription($description . "\n\nThis is fixture data for local development.");
            $event->setSubmittedBy($organiser);
            $event->setVenue('Main venue');
            $event->setUrl('https://example.com/' . $this->slug($title));

            if ($approved) {
                $event->approve();
            }

            $manager->persist($event);
        }

        foreach ([
            ['Thursday Kiz Social', 'berlin', 'Every Thursday, 21:00–01:00', 'Tanzhaus Mitte', 10],
            ['Soirée Urban Kiz', 'paris', 'Every Friday, 22:00–03:00', 'Le Studio', 5],
            ['Domingo Kiz', 'lisbon', 'Every Sunday, 19:00–23:00', 'Bar do Rio', 150],
            ['Kiz Praha Social', 'prague', 'Every second Wednesday, 20:00', 'Café Tanec', null],
        ] as [$title, $citySlug, $schedule, $venue, $confirmedDaysAgo]) {
            $social = new Event($title, EventTypeEnum::SOCIAL, $cities[$citySlug], $this->slug($title) . '-' . $citySlug);
            $social->setSchedule($schedule);
            $social->setVenue($venue);
            $social->setDescription('Weekly social. Beginner taster at the start, then open floor until close.');
            $social->setSubmittedBy($organiser);
            $social->approve();

            if ($confirmedDaysAgo !== null) {
                // Approving sets today; override to show the stale state.
                $reflection = new \ReflectionProperty(Event::class, 'lastConfirmedAt');
                $reflection->setValue($social, $today->modify(sprintf('-%d days', $confirmedDaysAgo)));
            }

            $manager->persist($social);
        }

        $manager->flush();
    }

    private function slug(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
    }
}
