<?php

namespace App\Focus\Infrastructure\Adapter;

use App\Focus\Application\Port\DashboardReadPort;
use App\Focus\Service\DashboardService;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\Employe;
use DateTimeImmutable;

final class LegacyDashboardReadAdapter implements DashboardReadPort
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    public function getAdminCards(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->dashboardService->getAdminCards($from, $to);
    }

    public function getAdminCarousels(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->dashboardService->getAdminCarousels($from, $to);
    }

    public function getAdminTabs(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->dashboardService->getAdminTabs($from, $to);
    }

    public function getMedecinCards(Employe $medecin, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->dashboardService->getMedecinCards($medecin, $from, $to);
    }

    public function getMedecinCarousels(Employe $medecin, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->dashboardService->getMedecinCarousels($medecin, $from, $to);
    }

    public function getMedecinTabs(Employe $medecin, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->dashboardService->getMedecinTabs($medecin, $from, $to);
    }

    public function getReceptionCards(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->dashboardService->getReceptionCards($from, $to);
    }

    public function getReceptionCarousels(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->dashboardService->getReceptionCarousels($from, $to);
    }

    public function getReceptionTabs(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->dashboardService->getReceptionTabs($from, $to);
    }
}
