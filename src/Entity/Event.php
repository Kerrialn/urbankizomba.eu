<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EventStatusEnum;
use App\Enum\EventTypeEnum;
use App\Repository\EventRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One entry on the calendar: a festival, a weekender, a workshop, a party, or a
 * recurring social.
 *
 * Submitted by a signed-in visitor and held as PENDING until somebody approves
 * it. Every public query filters on APPROVED, so a row that was never looked at
 * cannot leak onto a page by omission.
 *
 * Dated events carry startsAt (and optionally endsAt); a social carries a
 * schedule ("Every Thursday, 21:00") and a lastConfirmedAt, which is the date
 * somebody last said it was still running. The two are not both required:
 * {@see \App\DataTransferObject\EventSubmissionDto} validates the right pair.
 */
#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_event_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_event_status_starts', columns: ['status', 'starts_at'])]
#[ORM\Index(name: 'idx_event_city_status', columns: ['city_id', 'status'])]
class Event implements \Stringable
{
    public const int MAX_DESCRIPTION_LENGTH = 4_000;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 20, enumType: EventStatusEnum::class)]
    private EventStatusEnum $status = EventStatusEnum::PENDING;

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $venue = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $endsAt = null;

    /**
     * For a social: when it happens, in words. "Every Thursday, 21:00–02:00".
     * Words rather than a cron expression because the exceptions (not in
     * August, moves for a festival) are exactly what a visitor needs to read.
     */
    #[ORM\Column(length: 160, nullable: true)]
    private ?string $schedule = null;

    /**
     * For a social: when somebody last said it is still running. Shown on the
     * page so a visitor can judge how much to trust the listing.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastConfirmedAt = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $organiser = null;

    /**
     * Who is teaching or playing, as typed. A list of names, not a relation:
     * a directory of artists is a later iteration, if ever.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lineup = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ticketUrl = null;

    /**
     * Filename inside public/uploads/posters, or null. The file itself is
     * managed by {@see \App\Service\Event\PosterStorage}.
     */
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $poster = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $submittedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reviewNote = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Column(length: 140)]
        private string $title,
        #[ORM\Column(length: 20, enumType: EventTypeEnum::class)]
        private EventTypeEnum $type,
        #[ORM\ManyToOne(targetEntity: City::class, inversedBy: 'events')]
        #[ORM\JoinColumn(nullable: false)]
        private City $city,
        #[ORM\Column(length: 160)]
        private string $slug,
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
        $this->touch();
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getType(): EventTypeEnum
    {
        return $this->type;
    }

    public function setType(EventTypeEnum $type): void
    {
        $this->type = $type;
        $this->touch();
    }

    public function isRecurring(): bool
    {
        return $this->type->isRecurring();
    }

    public function getStatus(): EventStatusEnum
    {
        return $this->status;
    }

    public function isApproved(): bool
    {
        return $this->status === EventStatusEnum::APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === EventStatusEnum::PENDING;
    }

    public function approve(?string $note = null): void
    {
        $this->status = EventStatusEnum::APPROVED;
        $this->reviewNote = $note;
        $this->reviewedAt = new DateTimeImmutable();

        // Approval is itself a confirmation: whoever approved it has just
        // looked at the listing and believed it.
        if ($this->isRecurring() && ! $this->lastConfirmedAt instanceof DateTimeImmutable) {
            $this->lastConfirmedAt = new DateTimeImmutable();
        }
    }

    public function reject(?string $note = null): void
    {
        $this->status = EventStatusEnum::REJECTED;
        $this->reviewNote = $note;
        $this->reviewedAt = new DateTimeImmutable();
    }

    /**
     * An edit by the submitter sends the listing back for review: what was
     * approved is not what is on the page any more.
     */
    public function resubmit(): void
    {
        $this->status = EventStatusEnum::PENDING;
        $this->reviewNote = null;
        $this->reviewedAt = null;
        $this->touch();
    }

    public function getCity(): City
    {
        return $this->city;
    }

    public function setCity(City $city): void
    {
        $this->city = $city;
        $this->touch();
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function getVenue(): ?string
    {
        return $this->venue;
    }

    public function setVenue(?string $venue): void
    {
        $this->venue = $venue;
        $this->touch();
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
        $this->touch();
    }

    public function getStartsAt(): ?DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?DateTimeImmutable $startsAt): void
    {
        $this->startsAt = $startsAt;
        $this->touch();
    }

    public function getEndsAt(): ?DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?DateTimeImmutable $endsAt): void
    {
        $this->endsAt = $endsAt;
        $this->touch();
    }

    /**
     * The last day the event is on: endsAt when set, otherwise the start.
     */
    public function getLastDay(): ?DateTimeImmutable
    {
        return $this->endsAt ?? $this->startsAt;
    }

    public function isMultiDay(): bool
    {
        return $this->startsAt instanceof DateTimeImmutable
            && $this->endsAt instanceof DateTimeImmutable
            && $this->endsAt->format('Y-m-d') !== $this->startsAt->format('Y-m-d');
    }

    public function getSchedule(): ?string
    {
        return $this->schedule;
    }

    public function setSchedule(?string $schedule): void
    {
        $this->schedule = $schedule;
        $this->touch();
    }

    public function getLastConfirmedAt(): ?DateTimeImmutable
    {
        return $this->lastConfirmedAt;
    }

    public function confirm(): void
    {
        $this->lastConfirmedAt = new DateTimeImmutable();
    }

    /**
     * Whether nobody has vouched for this social within the window. Dated
     * events are never stale: they have a date, and the date passes.
     */
    public function isStale(int $days): bool
    {
        if (! $this->isRecurring()) {
            return false;
        }

        if (! $this->lastConfirmedAt instanceof DateTimeImmutable) {
            return true;
        }

        return $this->lastConfirmedAt < new DateTimeImmutable(sprintf('-%d days', $days));
    }

    public function getOrganiser(): ?string
    {
        return $this->organiser;
    }

    public function setOrganiser(?string $organiser): void
    {
        $this->organiser = $organiser;
        $this->touch();
    }

    public function getLineup(): ?string
    {
        return $this->lineup;
    }

    public function setLineup(?string $lineup): void
    {
        $this->lineup = $lineup;
        $this->touch();
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
        $this->touch();
    }

    public function getTicketUrl(): ?string
    {
        return $this->ticketUrl;
    }

    public function setTicketUrl(?string $ticketUrl): void
    {
        $this->ticketUrl = $ticketUrl;
        $this->touch();
    }

    public function getPoster(): ?string
    {
        return $this->poster;
    }

    public function setPoster(?string $poster): void
    {
        $this->poster = $poster;
        $this->touch();
    }

    public function getSubmittedBy(): ?User
    {
        return $this->submittedBy;
    }

    public function setSubmittedBy(?User $submittedBy): void
    {
        $this->submittedBy = $submittedBy;
    }

    public function isSubmittedBy(?User $user): bool
    {
        return $user instanceof User
            && $this->submittedBy instanceof User
            && $this->submittedBy->getId()->equals($user->getId());
    }

    public function getReviewNote(): ?string
    {
        return $this->reviewNote;
    }

    public function setReviewNote(?string $reviewNote): void
    {
        $this->reviewNote = $reviewNote;
    }

    public function getReviewedAt(): ?DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
