<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Subscriber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscriber>
 */
class SubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscriber::class);
    }

    public function findOneByEmail(string $email): ?Subscriber
    {
        return $this->findOneBy([
            'email' => mb_strtolower(trim($email)),
        ]);
    }

    public function findOneByToken(string $token): ?Subscriber
    {
        return $this->findOneBy([
            'token' => $token,
        ]);
    }

    /**
     * Who actually gets the newsletter: confirmed and not unsubscribed.
     *
     * @return list<Subscriber>
     */
    public function findConfirmed(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.confirmedAt IS NOT NULL')
            ->andWhere('s.unsubscribedAt IS NULL')
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countConfirmed(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.confirmedAt IS NOT NULL')
            ->andWhere('s.unsubscribedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
