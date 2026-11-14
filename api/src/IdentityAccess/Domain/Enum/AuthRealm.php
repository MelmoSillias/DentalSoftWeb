<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Enum;

use App\IdentityAccess\Domain\Entity\User;

enum AuthRealm: string
{
    case App = 'app';
    case Patient = 'patient';

    public static function forUser(User $user): ?self
    {
        $roles = $user->getRoles();
        $staff = array_intersect(['ROLE_ADMIN', 'ROLE_MEDECIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'], $roles) !== [];
        $patient = in_array('ROLE_PATIENT', $roles, true);

        return $staff && !$patient ? self::App : ($patient && !$staff ? self::Patient : null);
    }
}
