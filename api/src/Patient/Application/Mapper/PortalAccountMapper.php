<?php

namespace App\Patient\Application\Mapper;

use App\Patient\Domain\Entity\Patient;
use App\IdentityAccess\Domain\Entity\User;

final class PortalAccountMapper
{
    public function formatPortalAccount(Patient $patient): ?array
    {
        $user = $patient->getPortalUser();
        if (!$user instanceof User) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'active' => $user->isActive(),
            'roles' => $user->getRoles(),
            'defaultPassword' => '123',
        ];
    }
}
