<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CityRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Uid\Uuid;

/**
 * A city with a scene, or at least an event.
 *
 * Cities are rows rather than free text on the event so that "urban kiz in
 * Berlin" is one page with one URL, which is what a search engine can rank and
 * what a local can send to a visitor. A city typed into the submission form
 * that is not yet known is created on the spot; it becomes visible only once an
 * event in it is approved, so nothing an admin has not looked at reaches a
 * public page.
 */
#[ORM\Entity(repositoryClass: CityRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_city_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_city_name_country', columns: ['name', 'country'])]
class City implements \Stringable
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'city')]
    private Collection $events;

    public function __construct(
        #[ORM\Column(length: 120)]
        private string $name,
        /**
         * ISO 3166-1 alpha-2, upper case. Names come from symfony/intl so the
         * database never stores "Germany" next to "Deutschland".
         */
        #[ORM\Column(length: 2)]
        private string $country,
        #[ORM\Column(length: 140)]
        private string $slug,
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new DateTimeImmutable();
        $this->events = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): void
    {
        $this->country = strtoupper($country);
    }

    public function getCountryName(): string
    {
        return Countries::exists($this->country) ? Countries::getName($this->country, 'en') : $this->country;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function __toString(): string
    {
        return sprintf('%s, %s', $this->name, $this->getCountryName());
    }
}
