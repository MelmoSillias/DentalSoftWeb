<?php

namespace App\IdentityAccess\Application\Command\UpdateUser;

use App\IdentityAccess\Application\Port\UserAdminWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class UpdateUserHandler implements CommandHandler
{
    public function __construct(private readonly UserAdminWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(UpdateUserCommand $command): array
    {
        return $this->writePort->updateUser($command->data, $command->actor);
    }
}
