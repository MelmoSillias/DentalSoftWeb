<?php

namespace App\IdentityAccess\Application\Port;

interface UserAdminWritePort
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createUser(array $data, ?object $actor = null): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateUser(array $data, ?object $actor = null): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function deleteUser(array $data, ?object $actor = null): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function resetPassword(array $data, ?object $actor = null): array;
}
