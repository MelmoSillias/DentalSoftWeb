<?php

namespace App\Billing\Infrastructure\Adapter;

use App\Billing\Application\Port\FinanceReadPort;
use App\Billing\Infrastructure\Persistence\Doctrine\Entity\ModeDePaiement;
use App\Billing\Infrastructure\Persistence\Doctrine\Repository\ModeDePaiementRepository;
use App\Billing\Service\FinanceService;

final class LegacyFinanceReadAdapter implements FinanceReadPort
{
    public function __construct(
        private readonly FinanceService $financeService,
        private readonly ModeDePaiementRepository $paymentMethodRepo,
    ) {
    }

    public function listPaymentMethods(): array
    {
        $methods = $this->paymentMethodRepo->findClassics();

        return array_map(fn (ModeDePaiement $method) => $this->mapMethod($method), $methods);
    }

    public function getChartData(int $year): array
    {
        return [
            'year' => $year,
            'availableYears' => $this->financeService->getAvailableTransactionYears(),
            'months' => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
            'datasetsComptes' => $this->financeService->getGraphDatasetsParCompteComplet($year),
            'barSoldeChart' => $this->financeService->getBarPointChartData($year),
            'evolutionCapital' => $this->financeService->getEvolutionCapitalAnnuel($year),
        ];
    }

    public function getMonthlyCrossTable(int $year, int $month, string $type = 'revenue'): array
    {
        return $this->financeService->getMonthlyCrossTable($year, $month, $type);
    }

    public function getCrossTableDayOverview(string $date): array
    {
        return $this->financeService->getCrossTableDayOverview($date);
    }

    public function listFixedCharges(): array
    {
        return [
            'items' => $this->financeService->listFixedCharges(),
            'total' => $this->financeService->getFixedChargesTotal(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMethod(ModeDePaiement $method): array
    {
        return [
            'id' => $method->getId(),
            'libelle' => $method->getLibelle(),
            'type' => $method->getType(),
            'actif' => $method->isActif(),
            'notes' => $method->getNotes(),
            'autoValidate' => $method->isAutoValidated(),
        ];
    }
}
