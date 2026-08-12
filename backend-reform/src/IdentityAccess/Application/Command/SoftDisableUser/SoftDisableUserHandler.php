<?php

namespace App\IdentityAccess\Application\Command\SoftDisableUser;

use App\IdentityAccess\Domain\Exception\IdentityAccessDomainException;
use App\IdentityAccess\Domain\Exception\UserAlreadyDisabledException;
use App\IdentityAccess\Domain\Repository\UserRepository;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\TransactionManager;
use Throwable;

final class SoftDisableUserHandler implements CommandHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TransactionManager $transactionManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(SoftDisableUserCommand $command): array
    {
        try {
            return $this->transactionManager->transactional(function () use ($command): array {
                $user = $this->userRepository->findActiveById(UserId::fromInt($command->userId));
                if ($user === null) {
                    return ['error' => 'Utilisateur non trouvé', 'status' => 404];
                }

                $user->softDisable();
                $this->userRepository->save($user);

                return [
                    'success' => true,
                    'message' => 'Utilisateur désactivé.',
                    'userId' => $user->requireId()->toInt(),
                ];
            });
        } catch (UserAlreadyDisabledException) {
            return ['error' => 'Utilisateur déjà désactivé', 'status' => 400];
        } catch (IdentityAccessDomainException $e) {
            return ['error' => $e->getMessage(), 'status' => 400];
        } catch (Throwable $e) {
            return ['error' => 'Erreur : ' . $e->getMessage(), 'status' => 500];
        }
    }
}
