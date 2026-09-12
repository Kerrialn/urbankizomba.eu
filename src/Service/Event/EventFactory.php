<?php

declare(strict_types=1);

namespace App\Service\Event;

use App\DataTransferObject\EventSubmissionDto;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EventTypeEnum;
use App\Repository\CityRepository;
use App\Service\Slug\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Turns a submission into rows: the event, a city if it named a new one, and
 * the poster on disk. The same code path serves a first submission and an
 * edit, so the two cannot drift in what they store.
 */
final readonly class EventFactory
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CityRepository $cityRepository,
        private SlugGenerator $slugGenerator,
        private PosterStorage $posterStorage,
    ) {
    }

    public function create(EventSubmissionDto $dto, User $submitter): Event
    {
        $city = $this->resolveCity($dto);
        $type = $dto->type ?? EventTypeEnum::PARTY;

        $event = new Event(
            title: trim((string) $dto->title),
            type: $type,
            city: $city,
            slug: $this->slugGenerator->forEvent((string) $dto->title, $city->getSlug(), $type->isRecurring() ? null : $dto->startsAt),
        );
        $event->setSubmittedBy($submitter);

        $this->apply($event, $dto);

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    public function update(Event $event, EventSubmissionDto $dto): void
    {
        $city = $this->resolveCity($dto);
        $type = $dto->type ?? $event->getType();

        $event->setTitle(trim((string) $dto->title));
        $event->setType($type);
        $event->setCity($city);
        $event->setSlug($this->slugGenerator->forEvent(
            (string) $dto->title,
            $city->getSlug(),
            $type->isRecurring() ? null : $dto->startsAt,
            current: $event->getSlug(),
        ));

        $this->apply($event, $dto);
        $event->resubmit();

        $this->entityManager->flush();
    }

    private function apply(Event $event, EventSubmissionDto $dto): void
    {
        $recurring = $event->isRecurring();

        $event->setDescription(trim((string) $dto->description));
        $event->setVenue($this->blankToNull($dto->venue));
        $event->setAddress($this->blankToNull($dto->address));
        $event->setOrganiser($this->blankToNull($dto->organiser));
        $event->setLineup($this->blankToNull($dto->lineup));
        $event->setUrl($this->blankToNull($dto->url));
        $event->setTicketUrl($this->blankToNull($dto->ticketUrl));

        // Only the fields that belong to the type are kept. A social that was
        // a festival in a previous edit must not keep the festival's dates.
        $event->setStartsAt($recurring ? null : $dto->startsAt);
        $event->setEndsAt($recurring ? null : $dto->endsAt);
        $event->setSchedule($recurring ? $this->blankToNull($dto->schedule) : null);

        if ($dto->poster instanceof UploadedFile) {
            $this->posterStorage->remove($event->getPoster());
            $event->setPoster($this->posterStorage->store($dto->poster));
        }
    }

    /**
     * The chosen city, or the typed one: found if it already exists under
     * that name and country, created otherwise.
     */
    private function resolveCity(EventSubmissionDto $dto): City
    {
        if ($dto->city instanceof City) {
            return $dto->city;
        }

        $name = trim((string) $dto->newCityName);
        $country = strtoupper((string) $dto->newCityCountry);

        $existing = $this->cityRepository->findOneByNameAndCountry($name, $country);

        if ($existing instanceof City) {
            return $existing;
        }

        $city = new City($name, $country, $this->slugGenerator->forCity($name, $country));
        $this->entityManager->persist($city);

        return $city;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
