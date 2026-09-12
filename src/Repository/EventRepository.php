<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\City;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EventStatusEnum;
use App\Enum\EventTypeEnum;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function findOneBySlug(string $slug): ?Event
    {
        return $this->findOneBy([
            'slug' => $slug,
        ]);
    }

    public function findApprovedBySlug(string $slug): ?Event
    {
        return $this->findOneBy([
            'slug' => $slug,
            'status' => EventStatusEnum::APPROVED,
        ]);
    }

    /**
     * Upcoming dated events, soonest first. Returns a query builder so the
     * controller can paginate it; the filters are applied here so the list
     * page and the home page cannot disagree about what "upcoming" means.
     *
     * An event is upcoming until its last day has passed, so a festival that
     * started yesterday is still on the list today.
     */
    public function upcomingQuery(
        DateTimeImmutable $today,
        ?string $country = null,
        ?EventTypeEnum $type = null,
        ?DateTimeImmutable $month = null,
        ?City $city = null,
    ): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('e')
            ->join('e.city', 'c')
            ->addSelect('c')
            ->andWhere('e.status = :approved')
            ->andWhere('e.type <> :social')
            ->andWhere('COALESCE(e.endsAt, e.startsAt) >= :today')
            ->setParameter('approved', EventStatusEnum::APPROVED)
            ->setParameter('social', EventTypeEnum::SOCIAL)
            ->setParameter('today', $today->format('Y-m-d'))
            ->orderBy('e.startsAt', 'ASC')
            ->addOrderBy('e.title', 'ASC');

        if ($country !== null && $country !== '') {
            $queryBuilder
                ->andWhere('c.country = :country')
                ->setParameter('country', strtoupper($country));
        }

        if ($type instanceof EventTypeEnum) {
            $queryBuilder
                ->andWhere('e.type = :type')
                ->setParameter('type', $type);
        }

        if ($month instanceof DateTimeImmutable) {
            // Anything that overlaps the month, not only what starts in it.
            $from = $month->modify('first day of this month')->setTime(0, 0);
            $to = $from->modify('first day of next month');

            $queryBuilder
                ->andWhere('e.startsAt < :to')
                ->andWhere('COALESCE(e.endsAt, e.startsAt) >= :from')
                ->setParameter('from', $from->format('Y-m-d'))
                ->setParameter('to', $to->format('Y-m-d'));
        }

        if ($city instanceof City) {
            $queryBuilder
                ->andWhere('e.city = :city')
                ->setParameter('city', $city);
        }

        return $queryBuilder;
    }

    /**
     * @return list<Event>
     */
    public function findUpcoming(DateTimeImmutable $today, int $limit, ?City $city = null): array
    {
        return $this->upcomingQuery($today, city: $city)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Dated events starting within the window: what the newsletter lists.
     *
     * @return list<Event>
     */
    public function findStartingBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.city', 'c')
            ->addSelect('c')
            ->andWhere('e.status = :approved')
            ->andWhere('e.type <> :social')
            ->andWhere('e.startsAt >= :from')
            ->andWhere('e.startsAt < :to')
            ->setParameter('approved', EventStatusEnum::APPROVED)
            ->setParameter('social', EventTypeEnum::SOCIAL)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->orderBy('e.startsAt', 'ASC')
            ->addOrderBy('c.country', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Approved socials, optionally in one city. Ordered by country, city, then
     * title, which is the order the socials page groups them in.
     *
     * @return list<Event>
     */
    public function findSocials(?City $city = null): array
    {
        $queryBuilder = $this->createQueryBuilder('e')
            ->join('e.city', 'c')
            ->addSelect('c')
            ->andWhere('e.status = :approved')
            ->andWhere('e.type = :social')
            ->setParameter('approved', EventStatusEnum::APPROVED)
            ->setParameter('social', EventTypeEnum::SOCIAL)
            ->orderBy('c.country', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->addOrderBy('e.title', 'ASC');

        if ($city instanceof City) {
            $queryBuilder
                ->andWhere('e.city = :city')
                ->setParameter('city', $city);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Everything one person has submitted, newest first, whatever its status.
     *
     * @return list<Event>
     */
    public function findSubmittedBy(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.city', 'c')
            ->addSelect('c')
            ->andWhere('e.submittedBy = :user')
            ->setParameter('user', $user)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Country codes with at least one upcoming approved event, for the filter.
     *
     * @return list<string>
     */
    public function countriesWithUpcoming(DateTimeImmutable $today): array
    {
        $rows = $this->upcomingQuery($today)
            ->select('DISTINCT c.country AS country')
            ->resetDQLPart('orderBy')
            ->orderBy('c.country', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): string => (string) $row['country'], $rows);
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.status = :pending')
            ->setParameter('pending', EventStatusEnum::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function slugExists(string $slug): bool
    {
        return $this->count([
            'slug' => $slug,
        ]) > 0;
    }
}
