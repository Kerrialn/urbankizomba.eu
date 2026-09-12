<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LoginCode;
use App\Enum\VerificationTypeEnum;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginCode>
 */
class LoginCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginCode::class);
    }

    public function findLatestUsable(
        VerificationTypeEnum $type,
        string $destination,
        DateTimeImmutable $now,
    ): ?LoginCode {
        return $this->createQueryBuilder('c')
            ->andWhere('c.type = :type')
            ->andWhere('c.destination = :destination')
            ->andWhere('c.consumedAt IS NULL')
            ->andWhere('c.expiresAt > :now')
            ->setParameter('type', $type)
            ->setParameter('destination', $destination)
            ->setParameter('now', $now)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Issuing a new code retires any earlier one, so a forwarded or shoulder-read
     * older email cannot still be used.
     */
    public function consumeAllFor(VerificationTypeEnum $type, string $destination): void
    {
        $this->createQueryBuilder('c')
            ->update()
            ->set('c.consumedAt', ':now')
            ->andWhere('c.type = :type')
            ->andWhere('c.destination = :destination')
            ->andWhere('c.consumedAt IS NULL')
            ->setParameter('now', new DateTimeImmutable())
            ->setParameter('type', $type)
            ->setParameter('destination', $destination)
            ->getQuery()
            ->execute();
    }

    public function purgeExpired(DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('c')
            ->delete()
            ->andWhere('c.expiresAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
