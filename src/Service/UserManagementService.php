<?php

namespace App\Service;

use App\Entity\Employe;
use App\Entity\User;
use App\Repository\EmployeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManagementService
{
    public function __construct(
        private UserRepository $userRepo,
        private EmployeRepository $employeRepo,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
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

    public function createUser(array $data): array
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

        return ['success' => true, 'user_id' => $user->getId()];
    }

    public function updateUser(array $data): array
    {
        if (empty($data['user_id']) || empty($data['username'])) {
            return ['error' => 'Paramètres manquants', 'status' => 400];
        }

        $user = $this->userRepo->find($data['user_id']);
        if (!$user) {
            return ['error' => 'Utilisateur non trouvé', 'status' => 404];
        }

        $user->setUsername($data['username']);
        $this->em->flush();

        return ['success' => true];
    }

    public function resetPassword(array $data): array
    {
        if (empty($data['user_id']) || empty($data['password'])) {
            return ['error' => 'Paramètres manquants', 'status' => 400];
        }

        $user = $this->userRepo->find($data['user_id']);
        if (!$user) {
            return ['error' => 'Utilisateur non trouvé', 'status' => 404];
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $this->em->flush();

        return ['success' => true];
    }

    public function deleteUser(array $data): array
    {
        if (empty($data['user_id'])) {
            return ['error' => 'Paramètre user_id manquant', 'status' => 400];
        }

        $user = $this->userRepo->find($data['user_id']);
        if (!$user) {
            return ['error' => 'Utilisateur non trouvé', 'status' => 404];
        }

        $this->em->remove($user);
        $this->em->flush();

        return ['success' => true];
    }
}
