<?php

namespace App\IdentityAccess\Application\Command\ResetUserPassword;

use App\IdentityAccess\Application\Port\UserAdminWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class ResetUserPasswordHandler implements CommandHandler
{
    public function __construct(private readonly UserAdminWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(ResetUserPasswordCommand $command): array
    {
        return $this->writePort->resetPassword($command->data, $command->actor);
    }
}
