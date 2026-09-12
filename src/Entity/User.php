<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'user.email.already_used')]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\ManyToOne(targetEntity: Practice::class, inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Practice $practice = null;

    /**
     * The referral code this account arrived on, if it did.
     *
     * Held on the user rather than only in the session because a practice does
     * not exist yet at registration, and the sign-in code that comes next is
     * routinely opened on a phone — a different session entirely. This column is
     * what survives that, and it is spent when the practice is created.
     */
    #[ORM\Column(length: 12, nullable: true)]
    private ?string $referredByCode = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(length: 180)]
        private string $email
    )
    {
        $this->id = Uuid::v7();
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

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function getPractice(): ?Practice
    {
        return $this->practice;
    }

    public function setPractice(?Practice $practice): void
    {
        $this->practice = $practice;
    }

    public function getReferredByCode(): ?string
    {
        return $this->referredByCode;
    }

    public function setReferredByCode(?string $referredByCode): void
    {
        $this->referredByCode = $referredByCode;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function eraseCredentials(): void
    {
    }
}
