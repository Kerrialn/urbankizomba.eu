<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SubscriberRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A newsletter address.
 *
 * Double opt-in: the row is written when the form is submitted, and nothing is
 * ever sent to it until the confirmation link has been opened. The token is
 * both the confirmation and the unsubscribe key, and is never reused across
 * addresses.
 */
#[ORM\Entity(repositoryClass: SubscriberRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_subscriber_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_subscriber_token', columns: ['token'])]
class Subscriber implements \Stringable
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 64)]
    private string $token;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $unsubscribedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastSentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(length: 180)]
        private string $email,
    ) {
        $this->id = Uuid::v7();
        $this->token = bin2hex(random_bytes(24));
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmedAt instanceof DateTimeImmutable && ! $this->unsubscribedAt instanceof DateTimeImmutable;
    }

    public function getConfirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function confirm(): void
    {
        $this->confirmedAt ??= new DateTimeImmutable();
        // Confirming again after unsubscribing is a re-subscription.
        $this->unsubscribedAt = null;
    }

    public function getUnsubscribedAt(): ?DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }

    public function unsubscribe(): void
    {
        $this->unsubscribedAt = new DateTimeImmutable();
    }

    public function getLastSentAt(): ?DateTimeImmutable
    {
        return $this->lastSentAt;
    }

    public function markSent(): void
    {
        $this->lastSentAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return $this->email;
    }
}
