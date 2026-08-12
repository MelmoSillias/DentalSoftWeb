<?php

namespace App\IdentityAccess\Application\Port;

interface UserAdminReadPort
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getUserList(): array;
}
