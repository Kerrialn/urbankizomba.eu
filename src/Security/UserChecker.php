<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Nothing is checked yet: there is no banned or disabled state on an account.
 * Kept wired into the firewall so that adding one is a line here rather than a
 * change to security.php.
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
