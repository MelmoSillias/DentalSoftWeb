<?php

namespace App\Service;

use App\Entity\Employe;
use App\Entity\User;
use App\Repository\UserRepository;

final class NotificationRecipientResolver
{
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_RECEPTION = 'ROLE_RECEPTION';
    public const ROLE_RECEPTIONNISTE = 'ROLE_RECEPTIONNISTE';

    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    /**
     * @return list<User>
     */
    public function admins(?User $exclude = null): array
    {
        return $this->forRoles([self::ROLE_ADMIN], $exclude);
    }

    /**
     * @return list<User>
     */
    public function receptionists(?User $exclude = null): array
    {
        return $this->forRoles([self::ROLE_RECEPTION, self::ROLE_RECEPTIONNISTE], $exclude);
    }

    /**
     * @return list<User>
     */
    public function adminsAndReceptionists(?User $exclude = null): array
    {
        return $this->forRoles([
            self::ROLE_ADMIN,
            self::ROLE_RECEPTION,
            self::ROLE_RECEPTIONNISTE,
        ], $exclude);
    }

    /**
     * @param list<string> $roles
     * @return list<User>
     */
    public function forRoles(array $roles, ?User $exclude = null): array
    {
        $roles = array_values(array_filter(array_unique($roles)));

        if ($roles === []) {
            return [];
        }

        $users = $this->userRepository->findByRoles($roles);

        return $this->deduplicate($users, $exclude);
    }

    public function userForEmploye(?Employe $employe, ?User $exclude = null): ?User
    {
        $user = $employe?->getUser();

        if (!$user instanceof User) {
            return null;
        }

        if ($exclude && $user->getId() === $exclude->getId()) {
            return null;
        }

        return $user;
    }

    /**
     * @param iterable<User> $users
     * @return list<User>
     */
    private function deduplicate(iterable $users, ?User $exclude = null): array
    {
        $bucket = [];

        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            if ($exclude && $user->getId() === $exclude->getId()) {
                continue;
            }

            if (!$user->isNotificationsEnabled()) {
                continue;
            }

            $bucket[$user->getId() ?? spl_object_id($user)] = $user;
        }

        return array_values($bucket);
    }
}
