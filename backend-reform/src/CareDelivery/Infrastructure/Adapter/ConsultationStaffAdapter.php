<?php

namespace App\CareDelivery\Infrastructure\Adapter;

use App\CareDelivery\Application\Port\ConsultationStaffPort;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository\EmployeRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class ConsultationStaffAdapter implements ConsultationStaffPort
{
    public function __construct(
        private readonly EmployeRepository $employeRepo,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CacheInterface $cache,
    ) {
    }

    public function findEmployeForUser(?object $user): ?object
    {
        if (!$user) {
            return null;
        }

        return $this->employeRepo->findOneBy(['user' => $user]);
    }

    public function verifyUserPassword(object $user, string $plain): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        return $this->passwordHasher->isPasswordValid($user, $plain);
    }

    public function listMedecins(): array
    {
        return $this->cache->get('medecins.list', function (ItemInterface $item) {
            $item->expiresAfter(120);
            $employees = $this->employeRepo->FindAllMedecin();

            return array_map(fn ($employee) => $this->mapEmployee($employee), $employees);
        });
    }

    public function listInfirmiers(): array
    {
        return $this->cache->get('infirmiers.list', function (ItemInterface $item) {
            $item->expiresAfter(120);
            $employees = $this->employeRepo->findAllInfirmiers();

            return array_map(fn ($employee) => $this->mapEmployee($employee), $employees);
        });
    }

    public function invalidateStaffReferenceCache(): void
    {
        $this->cache->delete('medecins.list');
        $this->cache->delete('infirmiers.list');
    }

    private function mapEmployee(object $employee): array
    {
        return [
            'id' => $employee->getId(),
            'nom' => $employee->getNom(),
            'prenom' => $employee->getPrenom(),
            'fullName' => $employee->getFullName(),
            'fullname' => $employee->getFullName(),
            'name' => $employee->getFullName(),
            'label' => $employee->getFullName(),
            'fonction' => $employee->getFonction(),
            'type' => $employee->getType(),
            'dateEmbauche' => $employee->getDateEmbauche()->format('Y-m-d'),
            'comingDays' => $employee->getComingDaysInWeek(),
        ];
    }
}
