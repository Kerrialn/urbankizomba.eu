<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        // Deleted users are allowed to authenticate so they can be redirected
        // to the account reactivation page by DeletedUserListener.
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
