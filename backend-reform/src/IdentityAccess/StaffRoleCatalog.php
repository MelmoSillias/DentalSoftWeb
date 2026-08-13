<?php

namespace App\IdentityAccess;

use App\IdentityAccess\Entity\Employe;

final class StaffRoleCatalog
{
    public const INPUT_ADMIN = 'admin';
    public const INPUT_MEDECIN = 'medecin';
    public const INPUT_RECEPTIONNISTE = 'receptionniste';
    public const INPUT_PATIENT = 'patient';

    public const EMPLOYEE_TYPE_MEDECIN = 'Medecin';
    public const EMPLOYEE_TYPE_INFIRMIER = 'Infirmier';
    public const EMPLOYEE_TYPE_RECEPTIONNISTE = 'Receptionniste';
    public const EMPLOYEE_TYPE_ADMIN = 'Admin';
    public const EMPLOYEE_TYPE_AUTRE = 'Autre';

    /** @var list<string> */
    public const ALLOWED_EMPLOYEE_TYPES = [
        self::EMPLOYEE_TYPE_MEDECIN,
        self::EMPLOYEE_TYPE_INFIRMIER,
        self::EMPLOYEE_TYPE_RECEPTIONNISTE,
        self::EMPLOYEE_TYPE_ADMIN,
        self::EMPLOYEE_TYPE_AUTRE,
    ];

    /** @var list<string> */
    private const RECEPTION_ROLES = [
        'ROLE_RECEPTION',
        'ROLE_RECEPTIONNISTE',
        'ROLE_SECRETAIRE',
    ];

    public static function normalizeInputRole(?string $role): ?string
    {
        $normalized = strtolower(trim((string) $role));

        return match ($normalized) {
            'admin', 'administrateur', 'role_admin' => self::INPUT_ADMIN,
            'medecin', 'médecin', 'docteur', 'role_medecin' => self::INPUT_MEDECIN,
            'receptionniste', 'reception', 'secretaire', 'secrétaire', 'role_receptionniste', 'role_reception', 'role_secretaire' => self::INPUT_RECEPTIONNISTE,
            'patient', 'role_patient' => self::INPUT_PATIENT,
            default => null,
        };
    }

    /**
     * @return list<string>|null
     */
    public static function rolesForInput(?string $role): ?array
    {
        $normalized = self::normalizeInputRole($role);

        return match ($normalized) {
            self::INPUT_ADMIN => ['ROLE_ADMIN'],
            self::INPUT_MEDECIN => ['ROLE_MEDECIN'],
            self::INPUT_RECEPTIONNISTE => self::RECEPTION_ROLES,
            self::INPUT_PATIENT => ['ROLE_PATIENT'],
            default => null,
        };
    }

    public static function labelFromInput(?string $role): string
    {
        $normalized = self::normalizeInputRole($role);

        return match ($normalized) {
            self::INPUT_ADMIN => 'Admin',
            self::INPUT_MEDECIN => 'Medecin',
            self::INPUT_RECEPTIONNISTE => 'Receptionniste',
            self::INPUT_PATIENT => 'Patient',
            default => 'Receptionniste',
        };
    }

    /**
     * @param list<string> $roles
     */
    public static function labelFromRoles(array $roles): string
    {
        return self::labelFromInput(self::inputRoleFromRoles($roles));
    }

    /**
     * @param list<string> $roles
     */
    public static function inputRoleFromRoles(array $roles): string
    {
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return self::INPUT_ADMIN;
        }
        if (in_array('ROLE_MEDECIN', $roles, true)) {
            return self::INPUT_MEDECIN;
        }
        if (in_array('ROLE_PATIENT', $roles, true)) {
            return self::INPUT_PATIENT;
        }
        if (array_intersect(self::RECEPTION_ROLES, $roles) !== []) {
            return self::INPUT_RECEPTIONNISTE;
        }

        return self::INPUT_RECEPTIONNISTE;
    }

    public static function inputRoleFromEmployeeType(?string $type): ?string
    {
        $normalized = strtolower(trim((string) $type));

        return match ($normalized) {
            'admin' => self::INPUT_ADMIN,
            'medecin', 'médecin' => self::INPUT_MEDECIN,
            'infirmier', 'receptionniste', 'reception' => self::INPUT_RECEPTIONNISTE,
            default => null,
        };
    }

    public static function syncEmployeeTypeFromRole(Employe $employee, ?string $inputRole): void
    {
        $normalized = self::normalizeInputRole($inputRole);
        if ($normalized === null || $normalized === self::INPUT_PATIENT) {
            return;
        }

        $currentType = strtolower(trim((string) $employee->getType()));

        match ($normalized) {
            self::INPUT_ADMIN => $employee->setType(self::EMPLOYEE_TYPE_ADMIN),
            self::INPUT_MEDECIN => $employee->setType(self::EMPLOYEE_TYPE_MEDECIN),
            self::INPUT_RECEPTIONNISTE => $employee->setType(
                $currentType === 'infirmier'
                    ? self::EMPLOYEE_TYPE_INFIRMIER
                    : self::EMPLOYEE_TYPE_RECEPTIONNISTE
            ),
            default => null,
        };
    }

    public static function assertAllowedEmployeeType(?string $type): string
    {
        $value = trim((string) $type);
        if ($value === '' || !in_array($value, self::ALLOWED_EMPLOYEE_TYPES, true)) {
            throw new \InvalidArgumentException(
                'Type de poste invalide. Valeurs autorisees : Medecin, Infirmier, Receptionniste, Admin, Autre.'
            );
        }

        return $value;
    }
}
