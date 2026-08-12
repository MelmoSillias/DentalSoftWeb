<?php

namespace App\Scheduling\Infrastructure\Adapter;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\Employe;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository\EmployeRepository;
use App\Scheduling\Application\Port\RdvReadPort;
use App\Scheduling\Service\RdvService;
use DateTimeInterface;

final class LegacyRdvReadAdapter implements RdvReadPort
{
    public function __construct(
        private readonly RdvService $rdvService,
        private readonly EmployeRepository $employeRepository,
    ) {
    }

    public function getStatsForDate(DateTimeInterface $date): array
    {
        return $this->rdvService->getStatsForDate($date);
    }

    public function getStatsForRange(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->rdvService->getStatsForRange($start, $end);
    }

    public function getStatsForMedecinDate(DateTimeInterface $date, int $medecinId): array
    {
        $medecin = $this->employeRepository->find($medecinId);
        if (!$medecin instanceof Employe) {
            return ['error' => 'Médecin introuvable', 'status' => 404];
        }

        return $this->rdvService->getStatsForMedecinDate($date, $medecin);
    }

    public function listByDate(DateTimeInterface $date, ?int $medecinId = null, bool $excludeCancelled = false): array
    {
        $medecin = $this->resolveMedecin($medecinId);
        if ($medecinId !== null && $medecin === null) {
            return ['error' => 'Médecin introuvable', 'status' => 404];
        }

        return $this->rdvService->listByDate($date, $medecin, $excludeCancelled);
    }

    public function listByRange(
        DateTimeInterface $start,
        DateTimeInterface $end,
        ?int $medecinId = null,
        bool $excludeCancelled = false,
    ): array {
        return $this->rdvService->listByRange($start, $end, $medecinId, $excludeCancelled);
    }

    public function listPendingByRange(
        DateTimeInterface $start,
        DateTimeInterface $end,
        ?int $medecinId = null,
    ): array {
        return $this->rdvService->listPendingByRange($start, $end, $medecinId);
    }

    private function resolveMedecin(?int $medecinId): ?Employe
    {
        if ($medecinId === null) {
            return null;
        }

        $medecin = $this->employeRepository->find($medecinId);

        return $medecin instanceof Employe ? $medecin : null;
    }
}
