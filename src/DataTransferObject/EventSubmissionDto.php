<?php

declare(strict_types=1);

namespace App\DataTransferObject;

use App\Entity\City;
use App\Entity\Event;
use App\Enum\EventTypeEnum;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * What the submission form collects.
 *
 * A DTO rather than the entity: the status, the slug and the submitter are
 * ours to set, and binding a form straight onto Event would make each of them
 * a field a submitted request could reach.
 *
 * Which date fields are required depends on the type, so that is checked in a
 * callback rather than with per-field constraints.
 */
final class EventSubmissionDto
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 140)]
    public ?string $title = null;

    #[Assert\NotNull]
    public ?EventTypeEnum $type = null;

    /**
     * An existing city, or null when the submitter typed a new one below.
     */
    public ?City $city = null;

    #[Assert\Length(max: 120)]
    public ?string $newCityName = null;

    #[Assert\Country]
    public ?string $newCityCountry = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 20, max: Event::MAX_DESCRIPTION_LENGTH)]
    public ?string $description = null;

    #[Assert\Length(max: 160)]
    public ?string $venue = null;

    #[Assert\Length(max: 255)]
    public ?string $address = null;

    public ?DateTimeImmutable $startsAt = null;

    public ?DateTimeImmutable $endsAt = null;

    #[Assert\Length(max: 160)]
    public ?string $schedule = null;

    #[Assert\Length(max: 160)]
    public ?string $organiser = null;

    #[Assert\Length(max: 2_000)]
    public ?string $lineup = null;

    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 255)]
    public ?string $url = null;

    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 255)]
    public ?string $ticketUrl = null;

    #[Assert\Image(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        minWidth: 400,
        minHeight: 400,
    )]
    public ?UploadedFile $poster = null;

    public static function fromEvent(Event $event): self
    {
        $dto = new self();
        $dto->title = $event->getTitle();
        $dto->type = $event->getType();
        $dto->city = $event->getCity();
        $dto->description = $event->getDescription();
        $dto->venue = $event->getVenue();
        $dto->address = $event->getAddress();
        $dto->startsAt = $event->getStartsAt();
        $dto->endsAt = $event->getEndsAt();
        $dto->schedule = $event->getSchedule();
        $dto->organiser = $event->getOrganiser();
        $dto->lineup = $event->getLineup();
        $dto->url = $event->getUrl();
        $dto->ticketUrl = $event->getTicketUrl();

        return $dto;
    }

    public function wantsNewCity(): bool
    {
        return ! $this->city instanceof \App\Entity\City && trim((string) $this->newCityName) !== '';
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (! $this->city instanceof \App\Entity\City && trim((string) $this->newCityName) === '') {
            $context->buildViolation('event.validation.city_required')
                ->atPath('city')
                ->addViolation();
        }

        if ($this->wantsNewCity() && ($this->newCityCountry === null || $this->newCityCountry === '')) {
            $context->buildViolation('event.validation.country_required')
                ->atPath('newCityCountry')
                ->addViolation();
        }

        if (! $this->type instanceof EventTypeEnum) {
            return;
        }

        if ($this->type->isRecurring()) {
            if (trim((string) $this->schedule) === '') {
                $context->buildViolation('event.validation.schedule_required')
                    ->atPath('schedule')
                    ->addViolation();
            }

            return;
        }

        if (! $this->startsAt instanceof DateTimeImmutable) {
            $context->buildViolation('event.validation.start_required')
                ->atPath('startsAt')
                ->addViolation();

            return;
        }

        if ($this->endsAt instanceof DateTimeImmutable && $this->endsAt < $this->startsAt) {
            $context->buildViolation('event.validation.ends_before_starts')
                ->atPath('endsAt')
                ->addViolation();
        }
    }
}
