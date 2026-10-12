<?php

namespace App\IdentityAccess\Security;

use App\IdentityAccess\Entity\User;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserEnabledChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            throw new BadCredentialsException();
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
