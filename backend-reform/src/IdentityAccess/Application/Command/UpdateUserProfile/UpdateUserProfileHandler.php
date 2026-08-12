<?php

namespace App\IdentityAccess\Application\Command\UpdateUserProfile;

use App\IdentityAccess\Application\Port\AuthWritePort;
use App\IdentityAccess\Domain\Exception\IdentityAccessDomainException;
use App\IdentityAccess\Domain\Model\User;
use App\Shared\Application\Bus\CommandHandler;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class UpdateUserProfileHandler implements CommandHandler
{
    public function __construct(private readonly AuthWritePort $authWritePort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(UpdateUserProfileCommand $command): array
    {
        try {
            if (isset($command->data['username']) && is_string($command->data['username']) && $command->data['username'] !== '') {
                User::assertValidUsername($command->data['username']);
            }

            if (array_key_exists('email', $command->data) && $command->data['email'] !== null && $command->data['email'] !== '') {
                User::assertValidEmail((string) $command->data['email']);
            }
        } catch (IdentityAccessDomainException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $this->authWritePort->updateMe(
            $command->data,
            $command->photo,
            $command->uploadDir,
        );
    }
}
