<?php

namespace App\IdentityAccess\Service;

use App\Communication\Service\NotificationRecipientResolver;
use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Entity\User;
use App\Shared\Event\EntityActionEvent;
use App\Patient\Entity\Patient;
use App\Patient\Repository\PatientRepository;
use App\IdentityAccess\Repository\EmployeRepository;
use App\IdentityAccess\Repository\UserDeviceRepository;
use App\IdentityAccess\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManagementService
{
    private const USERS_LINK = '/admin/utilisateurs';

    public function __construct(
        private UserRepository $userRepo,
        private EmployeRepository $employeRepo,
        private PatientRepository $patientRepo,
        private UserDeviceRepository $userDeviceRepo,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private NotificationRecipientResolver $recipientResolver,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    private function normalizeRole(?string $role): ?string
    {
        $normalized = strtolower(trim((string) $role));

        return match ($normalized) {
            'admin', 'administrateur', 'role_admin' => 'admin',
            'medecin', 'docteur', 'role_medecin' => 'medecin',
            'secretaire', 'secretaire', 'reception', 'receptionniste', 'role_receptionniste', 'role_secretaire' => 'secretaire',
            'patient', 'role_patient' => 'patient',
            default => null,
        };
    }

    private function resolveRolesFromInput(?string $role): ?array
    {
        $normalized = $this->normalizeRole($role);

        return match ($normalized) {
            'admin' => ['ROLE_ADMIN'],
            'medecin' => ['ROLE_MEDECIN'], 
            'secretaire' => ['ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'],
            'patient' => ['ROLE_PATIENT'],
            default => null,
        };
    }

    private function normalizeOptionalId(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $id = (int) $value;
        return $id > 0 ? $id : null;
    }

    private function findEmployeeByUser(User $user): ?Employe
    {
        return $this->employeRepo->findOneBy(['user' => $user]);
    }

    private function findPatientByUser(User $user): ?Patient
    {
        return $this->patientRepo->findOneBy(['portalUser' => $user]);
    }

    private function roleLabelFromRoles(array $roles): string
    {
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return 'Admin';
        }
        if (in_array('ROLE_MEDECIN', $roles, true)) {
            return 'Medecin';
        }
        if (in_array('ROLE_PATIENT', $roles, true)) {
            return 'Patient';
        }

        return 'Secretaire';
    }

    public function getAvailableAssociations(): array
    {
        $employees = $this->employeRepo->findBy(['user' => null], ['nom' => 'ASC', 'prenom' => 'ASC']);
        $patients = $this->patientRepo->findBy(['portalUser' => null], ['nom' => 'ASC', 'prenom' => 'ASC']);

        return [
            'employees' => array_map(static fn(Employe $employee): array => [
                'id' => $employee->getId(),
                'nom' => $employee->getNom(),
                'prenom' => $employee->getPrenom(),
                'fullname' => trim(($employee->getNom() ?? '') . ' ' . ($employee->getPrenom() ?? '')),
                'type' => $employee->getType(),
            ], $employees),
            'patients' => array_map(static fn(Patient $patient): array => [
                'id' => $patient->getId(),
                'nom' => $patient->getNom(),
                'prenom' => $patient->getPrenom(),
                'fullname' => trim(($patient->getNom() ?? '') . ' ' . ($patient->getPrenom() ?? '')),
                'numCarnet' => $patient->getNumCarnet(),
            ], $patients),
        ];
    }

    public function getUserList(): array
    {
        $users = $this->userRepo->findAll();
        $userIds = array_values(array_filter(array_map(static fn(User $user) => $user->getId(), $users)));
        $deviceStatsRows = $this->userDeviceRepo->countStatsGroupedByUserIds($userIds);
        $deviceStats = [];
        foreach ($deviceStatsRows as $row) {
            $deviceStats[(int) $row['userId']] = [
                'approved' => (int) $row['approvedCount'],
                'pending' => (int) $row['pendingCount'],
                'total' => (int) $row['totalCount'],
            ];
        }

        $result = [];
        foreach ($users as $user) {
            $employee = $this->findEmployeeByUser($user);
            $patient = $this->findPatientByUser($user);
            $roles = $user->getRoles();
            $stats = $deviceStats[(int) $user->getId()] ?? ['approved' => 0, 'pending' => 0, 'total' => 0];

            $result[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'roles' => $roles,
                'role' => $this->roleLabelFromRoles($roles),
                'employee_id' => $employee ? $employee->getId() : null,
                'employee' => $employee ? [
                    'id' => $employee->getId(),
                    'nom' => $employee->getNom(),
                    'prenom' => $employee->getPrenom(),
                ] : null,
                'patient_id' => $patient ? $patient->getId() : null,
                'patient' => $patient ? [
                    'id' => $patient->getId(),
                    'nom' => $patient->getNom(),
                    'prenom' => $patient->getPrenom(),
                    'numCarnet' => $patient->getNumCarnet(),
                ] : null,
                'deviceStats' => $stats,
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

        $roles = $this->resolveRolesFromInput($data['role'] ?? null);
        if ($roles === null) {
            return ['error' => 'Role invalide. Choisissez Admin, Medecin, Secretaire ou Patient.', 'status' => 400];
        }

        $normalizedRole = $this->normalizeRole($data['role'] ?? null);
        $employeeId = $this->normalizeOptionalId($data['employee_id'] ?? null);
        $patientId = $this->normalizeOptionalId($data['patient_id'] ?? null);

        if ($employeeId !== null && $patientId !== null) {
            return ['error' => 'Associez soit un employe soit un patient, pas les deux.', 'status' => 400];
        }

        if ($normalizedRole === 'patient' && $employeeId !== null) {
            return ['error' => 'Un compte patient ne peut pas etre associe a un employe.', 'status' => 400];
        }

        if ($normalizedRole !== 'patient' && $patientId !== null) {
            return ['error' => 'Un compte staff ne peut pas etre associe a un patient.', 'status' => 400];
        }

        $employee = null;
        if ($employeeId !== null) {
            $employee = $this->employeRepo->find($employeeId);
            if (!$employee) {
                return ['error' => 'Employe introuvable', 'status' => 404];
            }
            if ($employee->getUser()) {
                return ['error' => 'Un utilisateur existe déjà pour cet employé', 'status' => 400];
            }
        }

        $patient = null;
        if ($patientId !== null) {
            $patient = $this->patientRepo->find($patientId);
            if (!$patient) {
                return ['error' => 'Patient introuvable', 'status' => 404];
            }
            if ($patient->getPortalUser()) {
                return ['error' => 'Un utilisateur existe deja pour ce patient', 'status' => 400];
            }
        }

        $user = new User();
        $user->setUsername($data['username']);
        $user->setRoles($roles);

        $defaultPassword = $data['password'] ?? '123';
        $hashedPassword = $this->passwordHasher->hashPassword($user, $defaultPassword);
        $user->setPassword($hashedPassword);

        if ($employee) {
            $employee->setUser($user);
            $this->em->persist($employee);
        }

        if ($patient) {
            $patient->setPortalUser($user);
            $this->em->persist($patient);
        }

        $this->em->persist($user);
        $this->em->flush();

        $message = sprintf(
            'Nouvel utilisateur %s créé (%s).',
            $user->getUsername(),
            implode(', ', $user->getRoles()),
        );
        $this->notifyAdmins($message, $actor, 'info', 'success');

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

        $username = trim((string) ($data['username'] ?? ''));
        if ($username === '') {
            return ['error' => 'Paramètres manquants', 'status' => 400];
        }

        $existing = $this->userRepo->findOneBy(['username' => $username]);
        if ($existing && $existing->getId() !== $user->getId()) {
            return ['error' => "Nom d'utilisateur déjà utilisé", 'status' => 400];
        }

        $roles = $this->resolveRolesFromInput($data['role'] ?? null);
        if ($roles === null) {
            return ['error' => 'Role invalide. Choisissez Admin, Medecin, Secretaire ou Patient.', 'status' => 400];
        }

        $normalizedRole = $this->normalizeRole($data['role'] ?? null);

        $currentEmployee = $this->findEmployeeByUser($user);
        $currentPatient = $this->findPatientByUser($user);

        $employeeId = array_key_exists('employee_id', $data)
            ? $this->normalizeOptionalId($data['employee_id'])
            : ($currentEmployee?->getId());
        $patientId = array_key_exists('patient_id', $data)
            ? $this->normalizeOptionalId($data['patient_id'])
            : ($currentPatient?->getId());

        if ($employeeId !== null && $patientId !== null) {
            return ['error' => 'Associez soit un employe soit un patient, pas les deux.', 'status' => 400];
        }

        if ($normalizedRole === 'patient' && $employeeId !== null) {
            return ['error' => 'Un compte patient ne peut pas etre associe a un employe.', 'status' => 400];
        }

        if ($normalizedRole !== 'patient' && $patientId !== null) {
            return ['error' => 'Un compte staff ne peut pas etre associe a un patient.', 'status' => 400];
        }

        $targetEmployee = null;
        if ($employeeId !== null) {
            $targetEmployee = $this->employeRepo->find($employeeId);
            if (!$targetEmployee) {
                return ['error' => 'Employe introuvable', 'status' => 404];
            }
            if ($targetEmployee->getUser() && $targetEmployee->getUser()?->getId() !== $user->getId()) {
                return ['error' => 'Un utilisateur existe déjà pour cet employé', 'status' => 400];
            }
        }

        $targetPatient = null;
        if ($patientId !== null) {
            $targetPatient = $this->patientRepo->find($patientId);
            if (!$targetPatient) {
                return ['error' => 'Patient introuvable', 'status' => 404];
            }
            if ($targetPatient->getPortalUser() && $targetPatient->getPortalUser()?->getId() !== $user->getId()) {
                return ['error' => 'Un utilisateur existe deja pour ce patient', 'status' => 400];
            }
        }

        $user->setUsername($username);
        $user->setRoles($roles);

        if ($currentEmployee && (!$targetEmployee || $currentEmployee->getId() !== $targetEmployee->getId())) {
            $currentEmployee->setUser(null);
            $this->em->persist($currentEmployee);
        }

        if ($currentPatient && (!$targetPatient || $currentPatient->getId() !== $targetPatient->getId())) {
            $currentPatient->setPortalUser(null);
            $this->em->persist($currentPatient);
        }

        if ($targetEmployee && $targetEmployee->getUser()?->getId() !== $user->getId()) {
            $targetEmployee->setUser($user);
            $this->em->persist($targetEmployee);
        }

        if ($targetPatient && $targetPatient->getPortalUser()?->getId() !== $user->getId()) {
            $targetPatient->setPortalUser($user);
            $this->em->persist($targetPatient);
        }

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
        $this->notifyAdmins($message, $actor, 'warning', 'warning');

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

        $employee = $this->findEmployeeByUser($user);
        if ($employee) {
            $employee->setUser(null);
            $this->em->persist($employee);
        }

        $patient = $this->findPatientByUser($user);
        if ($patient) {
            $patient->setPortalUser(null);
            $this->em->persist($patient);
        }

        $this->em->remove($user);
        $this->em->flush();

        $message = sprintf('Utilisateur %s supprimé.', $label);
        $this->notifyAdmins($message, $actor, 'warning', 'warning');

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
        string $priority = 'info',
        string $type = 'info',
    ): void {
        $recipients = $this->recipientResolver->admins($emitter);

        if ($recipients === []) {
            return;
        }

        $this->eventDispatcher->dispatch(
            new EntityActionEvent(
                $emitter ?? ($recipients[0] ?? new User()),
                'users_management',
                ['ROLE_ADMIN'],
                $emitter,
                [
                    'message' => $message,
                    'priority' => $priority,
                    'type' => $type,
                    'link' => self::USERS_LINK,
                ],
            )
        );
    }
}
