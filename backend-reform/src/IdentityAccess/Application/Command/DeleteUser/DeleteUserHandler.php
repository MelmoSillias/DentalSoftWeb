<?php

namespace App\IdentityAccess\Application\Command\DeleteUser;

use App\IdentityAccess\Application\Port\UserAdminWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class DeleteUserHandler implements CommandHandler
{
    public function __construct(private readonly UserAdminWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(DeleteUserCommand $command): array
    {
        return $this->writePort->deleteUser($command->data, $command->actor);
    }
}
