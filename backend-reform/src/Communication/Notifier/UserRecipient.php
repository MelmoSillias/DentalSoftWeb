<?php

namespace App\Communication\Notifier;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use Symfony\Component\Notifier\Recipient\Recipient;

class UserRecipient extends Recipient
{
    public function __construct(private User $user)
    {
        parent::__construct($user->getUserIdentifier());
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
