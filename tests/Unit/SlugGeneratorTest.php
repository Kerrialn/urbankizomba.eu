<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Repository\CityRepository;
use App\Repository\EventRepository;
use App\Service\Slug\SlugGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class SlugGeneratorTest extends TestCase
{
    public function testAnEventSlugCarriesCityAndYear(): void
    {
        $generator = $this->generator(takenEvents: []);

        self::assertSame(
            'urban-kiz-weekend-berlin-2026',
            $generator->forEvent('Urban Kiz Weekend', 'berlin', new DateTimeImmutable('2026-10-03')),
        );
    }

    public function testTheCityIsNotRepeatedWhenTheTitleAlreadyNamesIt(): void
    {
        $generator = $this->generator(takenEvents: []);

        self::assertSame(
            'berlin-social-club',
            $generator->forEvent('Berlin Social Club', 'berlin', null),
        );
    }

    public function testACollisionGetsANumericSuffix(): void
    {
        $generator = $this->generator(takenEvents: ['kiz-night-prague-2026', 'kiz-night-prague-2026-2']);

        self::assertSame(
            'kiz-night-prague-2026-3',
            $generator->forEvent('Kiz Night', 'prague', new DateTimeImmutable('2026-01-10')),
        );
    }

    public function testTheCurrentSlugIsNotACollisionWithItself(): void
    {
        $generator = $this->generator(takenEvents: ['kiz-night-prague-2026']);

        self::assertSame(
            'kiz-night-prague-2026',
            $generator->forEvent('Kiz Night', 'prague', new DateTimeImmutable('2026-01-10'), current: 'kiz-night-prague-2026'),
        );
    }

    public function testASecondCityOfTheSameNameGetsItsCountry(): void
    {
        $generator = $this->generator(takenCities: ['frankfurt']);

        self::assertSame('frankfurt-pl', $generator->forCity('Frankfurt', 'PL'));
    }

    public function testAccentsAreTransliterated(): void
    {
        $generator = $this->generator();

        self::assertSame('zurich', $generator->forCity('Zürich', 'CH'));
        self::assertSame('wroclaw', $generator->forCity('Wrocław', 'PL'));
    }

    /**
     * @param list<string> $takenEvents
     * @param list<string> $takenCities
     */
    private function generator(array $takenEvents = [], array $takenCities = []): SlugGenerator
    {
        $events = $this->createMock(EventRepository::class);
        $events->method('slugExists')->willReturnCallback(static fn (string $slug): bool => in_array($slug, $takenEvents, true));

        $cities = $this->createMock(CityRepository::class);
        $cities->method('findOneBySlug')->willReturnCallback(
            static fn (string $slug): ?\App\Entity\City => in_array($slug, $takenCities, true) ? new \App\Entity\City('x', 'XX', $slug) : null,
        );

        return new SlugGenerator(new AsciiSlugger(), $events, $cities);
    }
}
