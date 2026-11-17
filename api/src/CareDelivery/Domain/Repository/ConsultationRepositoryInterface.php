<?php

declare(strict_types=1);

namespace App\CareDelivery\Domain\Repository;

interface ConsultationRepositoryInterface
{
    public function NewConsultation(array $data, \App\Patient\Infrastructure\Persistence\Doctrine\PatientRepository $patientRepo, \App\IdentityAccess\Infrastructure\Persistence\Doctrine\EmployeRepository $empRepo, bool $flush = true): \App\CareDelivery\Domain\Entity\Consultation;

    public function nextNumeroPassageForDay(\DateTimeInterface $day): int;

    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array;

    public function findPendingConsultations(): array;

    public function findClosedConsultations(): array;

    public function findConsultationsByPatient($patientId): array;

    public function findLatestByPatient(\App\Patient\Domain\Entity\Patient|int $patient): ?\App\CareDelivery\Domain\Entity\Consultation;

    public function findLatestClosedByPatient(\App\Patient\Domain\Entity\Patient|int $patient): ?\App\CareDelivery\Domain\Entity\Consultation;

    public function findConsultationsByMedecin($medecinId): array;

    public function findConsultationsByInfirmier($infirmierId): array;

    public function findConsultationsBySalle($salleId): array;

    public function findConsultationsByDateRange($startDate, $endDate): array;

    public function findPendingConsultationsByMedecin($medecinId): array;

    public function findClosedConsultationsByMedecin($medecinId): array;

    public function findFullConsultation(int $id): ?\App\CareDelivery\Domain\Entity\Consultation;

    public function clearLegacyDevisReference(int $devisId): void;

    public function find(mixed $id): ?object;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;

    public function count(array $criteria = []): int;

    public function findAll(): array;
}
