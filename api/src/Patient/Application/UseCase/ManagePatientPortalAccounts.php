<?php

namespace App\Patient\Application\UseCase;

use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\UserRepository;
use App\Patient\Domain\Entity\Patient;
use App\Patient\Infrastructure\Persistence\Doctrine\PatientRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\Cache\CacheItemPoolInterface;

use App\Patient\Application\Mapper\PortalAccountMapper;

final class ManagePatientPortalAccounts
{
    public function __construct(
        private PatientRepository $patientRepo,
        private UserRepository $userRepo,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private PortalAccountMapper $accountMapper,
        private CacheInterface $cache,
    ) {
    }

    public function getPatientPortalAccountData(int $id): array
    {
        $patient = $this->findActivePatient($id);
        if (!$patient) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        }

        return [
            'success' => true,
            'account' => $this->accountMapper->formatPortalAccount($patient),
        ];
    }

    public function createPatientPortalAccount(int $id): array
    {
        $patient = $this->findActivePatient($id);
        if (!$patient) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        }

        if ($patient->getPortalUser() instanceof User) {
            return [
                'success' => true,
                'message' => 'Compte utilisateur deja lie au patient.',
                'account' => $this->accountMapper->formatPortalAccount($patient),
            ];
        }

        $this->ensurePatientPortalAccount($patient);
        $this->em->flush();

        return [
            'success' => true,
            'message' => 'Compte patient cree avec succes.',
            'account' => $this->accountMapper->formatPortalAccount($patient),
        ];
    }

    public function createMissingPatientPortalAccounts(): array
    {
        $patients = $this->patientRepo->createQueryBuilder('p')
            ->leftJoin('p.portalUser', 'portalUser')
            ->andWhere('portalUser.id IS NULL')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();

        $created = 0;

        foreach ($patients as $patient) {
            if (!$patient instanceof Patient) {
                continue;
            }

            $this->ensurePatientPortalAccount($patient);
            ++$created;
        }

        if ($created > 0) {
            $this->em->flush();
            $this->clearPatientsCache();
        }

        return [
            'success' => true,
            'createdCount' => $created,
            'message' => sprintf('%d compte(s) patient créé(s).', $created),
        ];
    }

    public function resetPatientPortalPassword(int $id): array
    {
        $patient = $this->findActivePatient($id);
        if (!$patient) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        }

        $user = $patient->getPortalUser();
        if (!$user instanceof User) {
            return ['error' => 'Aucun compte utilisateur lie a ce patient', 'status' => 404];
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, '123'));
        $this->em->persist($user);
        $this->em->flush();

        return [
            'success' => true,
            'message' => 'Mot de passe reinitialise a 123.',
            'account' => $this->accountMapper->formatPortalAccount($patient),
        ];
    }

    public function togglePatientPortalAccount(int $id, bool $active): array
    {
        $patient = $this->findActivePatient($id);
        if (!$patient) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        }

        $user = $patient->getPortalUser();
        if (!$user instanceof User) {
            return ['error' => 'Aucun compte utilisateur lie a ce patient', 'status' => 404];
        }

        $user->setActive($active);
        $this->em->persist($user);
        $this->em->flush();

        return [
            'success' => true,
            'message' => $active ? 'Compte active.' : 'Compte desactive.',
            'account' => $this->accountMapper->formatPortalAccount($patient),
        ];
    }

    public function ensurePatientPortalAccount(Patient $patient): User
    {
        $user = $patient->getPortalUser();
        if ($user instanceof User) {
            return $user;
        }

        $username = $this->buildUniquePatientUsername($patient);
        $user = new User();
        $user->setUsername($username);
        $user->setRoles(['ROLE_PATIENT']);
        $user->setActive(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, '123'));

        $patient->setPortalUser($user);
        $this->em->persist($user);
        $this->em->persist($patient);

        return $user;
    }

    private function buildUniquePatientUsername(Patient $patient): string
    {
        $raw = sprintf(
            '%s%s%s%s',
            $patient->getNom() ?? '',
            $patient->getPrenom() ?? '',
            $patient->getAge() ?? 0,
            $patient->getDateInscription()?->format('Ymd') ?? (new DateTime())->format('Ymd')
        );

        $base = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw) ?: $raw));
        if ($base === '') {
            $base = 'patient' . ($patient->getId() ?? (new DateTime())->format('His'));
        }

        $username = $base;
        $i = 1;
        while ($this->userRepo->findOneBy(['username' => $username]) !== null) {
            $i++;
            $username = $base . $i;
        }

        return $username;
    }

    private function findActivePatient(int $id): ?Patient
    {
        $patient = $this->patientRepo->find($id);

        if (!$patient instanceof Patient || $patient->isDeleted()) {
            return null;
        }

        return $patient;
    }

    private function clearPatientsCache(): void
    {
        if ($this->cache instanceof CacheItemPoolInterface) {
            $this->cache->clear();
        }
    }
}
