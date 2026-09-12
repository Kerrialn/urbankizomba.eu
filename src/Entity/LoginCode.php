<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\VerificationTypeEnum;
use App\Repository\LoginCodeRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A single-use one-time code issued to a destination (an email address today,
 * a phone number when SMS login is added — hence `type`).
 *
 * The code itself is never stored: only a keyed hash, so a database leak does
 * not hand over live codes. Rows are short-lived and purged after expiry.
 */
#[ORM\Entity(repositoryClass: LoginCodeRepository::class)]
#[ORM\Index(name: 'idx_login_code_destination', columns: ['destination', 'expires_at'])]
class LoginCode
{
    public const int MAX_ATTEMPTS = 5;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $consumedAt = null;

    #[ORM\Column(type: Types::SMALLINT, options: [
        'default' => 0,
    ])]
    private int $attempts = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(length: 20, enumType: VerificationTypeEnum::class)]
        private VerificationTypeEnum $type,
        /**
         * Normalised: lower-cased email today, E.164 phone number later.
         */
        #[ORM\Column(length: 180)]
        private string $destination,
        #[ORM\Column(length: 64)]
        private string $codeHash,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $expiresAt,
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getType(): VerificationTypeEnum
    {
        return $this->type;
    }

    public function getDestination(): string
    {
        return $this->destination;
    }

    public function getCodeHash(): string
    {
        return $this->codeHash;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function recordFailedAttempt(): void
    {
        ++$this->attempts;
    }

    public function consume(): void
    {
        $this->consumedAt = new DateTimeImmutable();
    }

    public function isUsable(DateTimeImmutable $now): bool
    {
        return ! $this->consumedAt instanceof \DateTimeImmutable
            && $this->attempts < self::MAX_ATTEMPTS
            && $this->expiresAt > $now;
    }
}
