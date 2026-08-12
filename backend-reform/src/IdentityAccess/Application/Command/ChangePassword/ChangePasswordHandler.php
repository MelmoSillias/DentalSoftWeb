<?php

namespace App\IdentityAccess\Application\Command\ChangePassword;

use App\IdentityAccess\Application\Port\AuthWritePort;
use App\Shared\Application\Bus\CommandHandler;

final class ChangePasswordHandler implements CommandHandler
{
    public function __construct(private readonly AuthWritePort $authWritePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(ChangePasswordCommand $command): array
    {
        return $this->authWritePort->changePassword($command->data);
    }
}
