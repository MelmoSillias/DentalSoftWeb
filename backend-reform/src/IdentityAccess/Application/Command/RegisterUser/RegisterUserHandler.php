<?php

namespace App\IdentityAccess\Application\Command\RegisterUser;

use App\IdentityAccess\Application\Port\AuthWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class RegisterUserHandler implements CommandHandler
{
    public function __construct(private readonly AuthWritePort $authWritePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(RegisterUserCommand $command): array
    {
        return $this->authWritePort->register($command->data);
    }
}
