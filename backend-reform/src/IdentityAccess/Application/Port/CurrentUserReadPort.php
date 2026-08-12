<?php

namespace App\IdentityAccess\Application\Port;

interface CurrentUserReadPort
{
    /**
     * @return array<string, mixed>
     */
    public function getCurrentUserData(): array;
}
