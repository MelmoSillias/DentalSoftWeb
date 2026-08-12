<?php

namespace App\IdentityAccess\Domain\Repository;

use App\IdentityAccess\Domain\Model\User;
use App\IdentityAccess\Domain\ValueObject\UserId;

interface UserRepository
{
    public function save(User $user): void;

    public function findById(UserId $id): ?User;

    public function findActiveById(UserId $id): ?User;
}
