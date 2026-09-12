<?php

declare(strict_types=1);

namespace App\Service\Slug;

use App\Repository\CityRepository;
use App\Repository\EventRepository;
use DateTimeImmutable;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * URL slugs that are unique and, where possible, meaningful.
 *
 * An event slug carries its city and year ("urban-kiz-weekend-berlin-2026"):
 * the same festival runs every year, and the year is what a visitor pasting the
 * link into a group chat needs to be visible. Collisions get a numeric suffix
 * rather than a random one, so the URL stays readable.
 */
final readonly class SlugGenerator
{
    public function __construct(
        private SluggerInterface $slugger,
        private EventRepository $eventRepository,
        private CityRepository $cityRepository,
    ) {
    }

    public function forEvent(string $title, string $citySlug, ?DateTimeImmutable $startsAt, ?string $current = null): string
    {
        $parts = [$this->slugger->slug($title)->lower()->toString()];

        // Only add the city when the title does not already say it, or the
        // slug reads "berlin-social-berlin".
        if (! str_contains($parts[0], $citySlug)) {
            $parts[] = $citySlug;
        }

        if ($startsAt instanceof DateTimeImmutable) {
            $parts[] = $startsAt->format('Y');
        }

        $base = $this->trim(implode('-', $parts), 150);

        return $this->unique($base, fn (string $slug): bool => $slug !== $current && $this->eventRepository->slugExists($slug));
    }

    public function forCity(string $name, string $country): string
    {
        $base = $this->trim($this->slugger->slug($name)->lower()->toString(), 130);

        // A second city of the same name in another country gets its country
        // appended, so "frankfurt" and "frankfurt-pl" rather than "frankfurt-2".
        if ($this->cityRepository->findOneBySlug($base) instanceof \App\Entity\City) {
            $base .= '-' . strtolower($country);
        }

        return $this->unique($base, fn (string $slug): bool => $this->cityRepository->findOneBySlug($slug) instanceof \App\Entity\City);
    }

    /**
     * @param callable(string): bool $taken
     */
    private function unique(string $base, callable $taken): string
    {
        $slug = $base;
        $suffix = 2;

        while ($taken($slug)) {
            $slug = sprintf('%s-%d', $base, $suffix);
            ++$suffix;
        }

        return $slug;
    }

    private function trim(string $slug, int $length): string
    {
        $slug = substr($slug, 0, $length);

        return rtrim($slug, '-') !== '' ? rtrim($slug, '-') : 'event';
    }
}
