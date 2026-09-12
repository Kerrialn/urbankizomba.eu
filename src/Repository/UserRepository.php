<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Practices that belong to a member of staff rather than to a customer.
     *
     * A real customer never gets ROLE_ADMIN — admin is access to the backend of
     * Kaltra itself, not to a practice — so the role is a reliable marker for
     * "this is one of ours". The alternative was a flag on Practice, which says
     * what it means but needs a migration and remembering to set it; this cannot
     * be forgotten.
     *
     * Raw SQL because `roles` is a JSON column: Postgres has no LIKE for json,
     * and DQL offers no way to cast. Substring matching is enough for a value
     * as distinctive as ROLE_ADMIN.
     *
     * @return list<string> practice ids, as strings
     */
    public function practiceIdsForAdmins(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            <<<'SQL'
                SELECT DISTINCT practice_id
                FROM "user"
                WHERE practice_id IS NOT NULL
                  AND roles::text LIKE :role
                SQL,
            [
                'role' => '%ROLE_ADMIN%',
            ],
        );

        return array_map(strval(...), $rows);
    }
}
