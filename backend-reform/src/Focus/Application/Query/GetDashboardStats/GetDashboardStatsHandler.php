<?php

namespace App\Focus\Application\Query\GetDashboardStats;

use App\Focus\Application\Port\DashboardReadPort;
use App\Focus\Domain\Exception\FocusDomainException;
use App\Focus\Domain\Model\FocusSnapshot;
use App\Shared\Application\Bus\QueryHandler;

final class GetDashboardStatsHandler implements QueryHandler
{
    public function __construct(private readonly DashboardReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetDashboardStatsQuery $query): array
    {
        $medecinId = $query->medecin?->getId();
        FocusSnapshot::create($query->role, $query->type, $query->from, $query->to, $medecinId);

        $role = strtolower($query->role);
        $type = strtolower($query->type);

        return match ($role) {
            'admin' => match ($type) {
                'cards' => $this->readPort->getAdminCards($query->from, $query->to),
                'carousels' => $this->readPort->getAdminCarousels($query->from, $query->to),
                'tabs' => $this->readPort->getAdminTabs($query->from, $query->to),
                default => throw new FocusDomainException('Unsupported dashboard type.'),
            },
            'medecin' => $this->handleMedecin($query, $type),
            'reception' => match ($type) {
                'cards' => $this->readPort->getReceptionCards($query->from, $query->to),
                'carousels' => $this->readPort->getReceptionCarousels($query->from, $query->to),
                'tabs' => $this->readPort->getReceptionTabs($query->from, $query->to),
                default => throw new FocusDomainException('Unsupported dashboard type.'),
            },
            default => throw new FocusDomainException('Unsupported dashboard role.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function handleMedecin(GetDashboardStatsQuery $query, string $type): array
    {
        if ($query->medecin === null) {
            throw new FocusDomainException('Medecin is required for medecin dashboard.');
        }

        return match ($type) {
            'cards' => $this->readPort->getMedecinCards($query->medecin, $query->from, $query->to),
            'carousels' => $this->readPort->getMedecinCarousels($query->medecin, $query->from, $query->to),
            'tabs' => $this->readPort->getMedecinTabs($query->medecin, $query->from, $query->to),
            default => throw new FocusDomainException('Unsupported dashboard type.'),
        };
    }
}
