<?php

namespace App\Service;

use App\Entity\Employe;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\EmployeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Func;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManagementService
{
    private const USERS_LINK = '/admin/utilisateurs';

    public function __construct(
        private UserRepository $userRepo,
        private EmployeRepository $employeRepo,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private NotificationService $notificationService,
        private NotificationRecipientResolver $recipientResolver,
        private EmployeeService $employeeService,
    ) {
    }

    private function resolveRoles(?Employe $employee): array
    {
        if (!$employee) {
            return ['ROLE_USER'];
        }

        return match (strtolower($employee->getType() ?? '')) {
            'admin', 'administrateur' => ['ROLE_ADMIN'],
            'medecin' => ['ROLE_MEDECIN'],
            'receptionniste' => ['ROLE_RECEPTIONNISTE'],
            default => ['ROLE_USER'],
        };
    }

    public function getUserList(): array
    {
        $users = $this->userRepo->findAll();
        $result = [];
        foreach ($users as $user) {
            $employee = $this->employeeService->getEmployeeByUser($user);

            $result[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'roles' => $user->getRoles(), 
                'employee_id' => $employee ? $employee->getId() : null,
                'employee' => $employee ? sprintf('%s %s', $employee->getNom(), $employee->getPrenom()) : null,
            ];
        }
        return $result;
    }

    public function createUser(array $data, ?User $actor = null): array
    {
        if (empty($data['username'])) {
            return ['error' => 'Username manquant', 'status' => 400];
        }

        if ($this->userRepo->findOneBy(['username' => $data['username']])) {
            return ['error' => "Nom d'utilisateur déjà utilisé", 'status' => 400];
        }

        $employee = null;
        if (!empty($data['employee_id'])) {
            $employee = $this->employeRepo->find($data['employee_id']);
            if ($employee?->getUser()) {
                return ['error' => 'Un utilisateur existe déjà pour cet employé', 'status' => 400];
            }
        }

        $user = new User();
        $user->setUsername($data['username']);
        $user->setRoles($this->resolveRoles($employee));

        $defaultPassword = $data['password'] ?? '123';
        $hashedPassword = $this->passwordHasher->hashPassword($user, $defaultPassword);
        $user->setPassword($hashedPassword);

        if ($employee) {
            $employee->setUser($user);
            $this->em->persist($employee);
        }

        $this->em->persist($user);
        $this->em->flush();

        $message = sprintf(
            'Nouvel utilisateur %s créé (%s).',
            $user->getUsername(),
            implode(', ', $user->getRoles()),
        );
        $this->notifyAdmins($message, $actor, Notification::PRIORITY_INFO, Notification::TYPE_SUCCESS);

        return ['success' => true, 'user_id' => $user->getId()];
    }

    public function updateUser(array $data, ?User $actor = null): array
    {
        $userId = $this->extractUserId($data);

        if (!$userId || empty($data['username'])) {
            return ['error' => 'Paramètres manquants', 'status' => 400];
        }

        $user = $this->userRepo->find($userId);
        if (!$user) {
            return ['error' => 'Utilisateur non trouvé', 'status' => 404];
        }

        $user->setUsername($data['username']);
        $this->em->flush();

        $message = sprintf('Profil utilisateur %s mis à jour.', $this->userLabel($user));
        $this->notifyAdmins($message, $actor);

        return ['success' => true];
    }

    public function resetPassword(array $data, ?User $actor = null): array
    {
        $userId = $this->extractUserId($data);

        if (!$userId || empty($data['password'])) {
            return ['error' => 'Paramètres manquants', 'status' => 400];
        }

        $user = $this->userRepo->find($userId);
        if (!$user) {
            return ['error' => 'Utilisateur non trouvé', 'status' => 404];
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $this->em->flush();

        $message = sprintf('Mot de passe réinitialisé pour %s.', $this->userLabel($user));
        $this->notifyAdmins($message, $actor, Notification::PRIORITY_WARNING, Notification::TYPE_WARNING);

        return ['success' => true];
    }

    public function deleteUser(array $data, ?User $actor = null): array
    {
        $userId = $this->extractUserId($data);

        if (!$userId) {
            return ['error' => 'Paramètre user_id manquant', 'status' => 400];
        }

        $user = $this->userRepo->find($userId);
        if (!$user) {
            return ['error' => 'Utilisateur non trouvé', 'status' => 404];
        }

        $label = $this->userLabel($user);

        $this->em->remove($user);
        $this->em->flush();

        $message = sprintf('Utilisateur %s supprimé.', $label);
        $this->notifyAdmins($message, $actor, Notification::PRIORITY_WARNING, Notification::TYPE_WARNING);

        return ['success' => true];
    }

    private function extractUserId(array $data): ?int
    {
        $id = $data['user_id'] ?? $data['id'] ?? null;

        return $id !== null ? (int) $id : null;
    }

    private function userLabel(User $user): string
    {
        return sprintf('%s (#%d)', $user->getUsername(), $user->getId());
    }

    private function notifyAdmins(
        string $message,
        ?User $emitter = null,
        string $priority = Notification::PRIORITY_INFO,
        string $type = Notification::TYPE_INFO,
    ): void {
        $recipients = $this->recipientResolver->admins($emitter);

        if ($recipients === []) {
            return;
        }

        $this->notificationService->notifyMany(
            $recipients,
            $message,
            $priority,
            self::USERS_LINK,
            $type,
            $emitter,
        );
    }
}
