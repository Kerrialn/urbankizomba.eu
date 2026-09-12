<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\City;
use App\Enum\EventStatusEnum;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<City>
 */
class CityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, City::class);
    }

    public function findOneBySlug(string $slug): ?City
    {
        return $this->findOneBy([
            'slug' => $slug,
        ]);
    }

    public function findOneByNameAndCountry(string $name, string $country): ?City
    {
        return $this->createQueryBuilder('c')
            ->andWhere('LOWER(c.name) = :name')
            ->andWhere('c.country = :country')
            ->setParameter('name', mb_strtolower(trim($name)))
            ->setParameter('country', strtoupper($country))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Every city, for the submission form's select. Ordered by country then
     * name so the optgroups come out in a stable order.
     *
     * @return list<City>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.country', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cities with something approved on them, with how much: upcoming dated
     * events and current socials counted separately, because a city with six
     * socials and no festival is a different place to visit from the reverse.
     *
     * One grouped query rather than a count per city; the cities index shows
     * every one of them at once.
     *
     * @return list<array{city: City, upcoming: int, socials: int}>
     */
    public function findActiveWithCounts(DateTimeImmutable $today): array
    {
        /** @var list<array{0: City, upcoming: int|string, socials: int|string}> $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('c AS city')
            ->addSelect('SUM(CASE WHEN e.type <> :social AND COALESCE(e.endsAt, e.startsAt) >= :today THEN 1 ELSE 0 END) AS upcoming')
            ->addSelect('SUM(CASE WHEN e.type = :social THEN 1 ELSE 0 END) AS socials')
            ->join('c.events', 'e', 'WITH', 'e.status = :approved')
            ->groupBy('c.id')
            ->orderBy('c.country', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->setParameter('approved', EventStatusEnum::APPROVED)
            ->setParameter('social', 'social')
            ->setParameter('today', $today->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        $result = [];

        foreach ($rows as $row) {
            $upcoming = (int) $row['upcoming'];
            $socials = (int) $row['socials'];

            // A city whose only approved events are in the past has nothing to
            // show. It stays in the database; it just is not listed.
            if ($upcoming === 0 && $socials === 0) {
                continue;
            }

            $result[] = [
                'city' => $row['city'] ?? $row[0],
                'upcoming' => $upcoming,
                'socials' => $socials,
            ];
        }

        return $result;
    }
}
