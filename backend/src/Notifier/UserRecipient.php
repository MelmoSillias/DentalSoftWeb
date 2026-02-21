<?php

namespace App\Notifier;

use App\Entity\User;
use Symfony\Component\Notifier\Recipient\Recipient;

class UserRecipient extends Recipient
{
    public function __construct(private User $user)
    {
        parent::__construct($user->getEmail() ?? '');
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
