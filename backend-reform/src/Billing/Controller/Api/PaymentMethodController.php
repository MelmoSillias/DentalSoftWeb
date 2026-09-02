<?php

namespace App\Billing\Controller\Api;

use App\Billing\Entity\ModeDePaiement;
use App\Billing\Repository\ModeDePaiementRepository;
use App\Focus\Service\FocusRealtimePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class PaymentMethodController extends AbstractController
{
    public function __construct(
        private ModeDePaiementRepository $paymentMethodRepo,
        private EntityManagerInterface $em,
        private \App\Billing\Service\FinanceService $financeService,
        private FocusRealtimePublisher $focusRealtimePublisher,
    ) {}

    #[Route('/api/payment-methods', name: 'api_modes_paiement_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $methods = $this->paymentMethodRepo->findClassics();
        $data = array_map(fn(ModeDePaiement $method) => $this->mapMethod($method), $methods);

        return $this->json($data);
    }

    #[Route('/api/payment-methods', name: 'api_modes_paiement_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $libelle = $data['nom'] ?? $data['libelle'] ?? null;
        if (!$data || !$libelle) {
            return $this->json(['error' => 'Nom requis'], 400);
        }

        if ($this->isInsurancePayload($data)) {
            return $this->json(['error' => 'Les assurances ne sont plus gerées dans les modes de paiement.'], 400);
        }

        $method = new ModeDePaiement();
        $this->applyMethodPayload($method, $data);
        $method->setActif(true);

        $this->em->persist($method);
        $this->em->flush();
        $this->focusRealtimePublisher->publishPaymentMethodRefresh($method, 'created');

        return $this->json($this->mapMethod($method), 201);
    }

    #[Route('/api/payment-methods/{id}', name: 'api_modes_paiement_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $method = $this->paymentMethodRepo->find($id);
        if (!$method) {
            return $this->json(['error' => 'Mode de paiement non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $libelle = $data['nom'] ?? $data['libelle'] ?? null;

        if ($this->isInsurancePayload($data, $method)) {
            return $this->json(['error' => 'Les assurances ne sont plus gerées dans les modes de paiement.'], 400);
        }

        if ($libelle) {
            $method->setLibelle($libelle);
        }
        $this->applyMethodPayload($method, $data, false);
        if (array_key_exists('actif', $data)) {
            $method->setActif((bool) $data['actif']);
        }

        $this->em->flush();
        $this->focusRealtimePublisher->publishPaymentMethodRefresh($method, 'updated');

        return $this->json($this->mapMethod($method));
    }

    #[Route('/api/payment-methods/{id}', name: 'api_modes_paiement_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $method = $this->paymentMethodRepo->find($id);
        if (!$method) {
            return $this->json(['error' => 'Mode de paiement non trouvé'], 404);
        }

        $this->focusRealtimePublisher->publishPaymentMethodRefresh($method, 'deleted');
        $this->em->remove($method);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/api/payment-methods/{id}/toggle', name: 'api_modes_paiement_toggle', methods: ['PATCH'])]
    public function toggle(int $id): JsonResponse
    {
        $method = $this->paymentMethodRepo->find($id);
        if (!$method) {
            return $this->json(['error' => 'Mode de paiement non trouvé'], 404);
        }

        $method->setActif(!$method->isActif());
        $this->em->flush();
        $this->focusRealtimePublisher->publishPaymentMethodRefresh($method, 'toggled');

        return $this->json($this->mapMethod($method));
    }

    #[Route('/api/finances/chart-data', name: 'api_finances_chart_data', methods: ['GET'])]
    public function getChartData(Request $request): JsonResponse
    {
        $selectedYear = $request->query->getInt('year', (int) date('Y'));

        return $this->json([
            'year' => $selectedYear,
            'availableYears' => $this->financeService->getAvailableTransactionYears(),
            'months' => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
            'datasetsComptes' => $this->financeService->getGraphDatasetsParCompteComplet($selectedYear),
            'barSoldeChart' => $this->financeService->getBarPointChartData($selectedYear),
            'evolutionCapital' => $this->financeService->getEvolutionCapitalAnnuel($selectedYear),
        ]);
    }

    #[Route('/api/finances/cross-table', name: 'api_finances_cross_table', methods: ['GET'])]
    public function getCrossTable(Request $request): JsonResponse
    {
        $year = $request->query->getInt('year', (int) date('Y'));
        $month = $request->query->getInt('month', (int) date('n'));
        $type = (string) $request->query->get('type', 'revenue');

        if ($month < 1 || $month > 12) {
            return $this->json(['error' => 'Le mois doit être compris entre 1 et 12.'], 400);
        }

        return $this->json($this->financeService->getMonthlyCrossTable($year, $month, $type));
    }

    #[Route('/api/finances/cross-table/day-overview', name: 'api_finances_cross_table_day_overview', methods: ['GET'])]
    public function getCrossTableDayOverview(Request $request): JsonResponse
    {
        $date = (string) $request->query->get('date', '');
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->json(['error' => 'Le paramètre date (YYYY-MM-DD) est requis.'], 400);
        }

        try {
            return $this->json($this->financeService->getCrossTableDayOverview($date));
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        }
    }

    #[Route('/api/finances/cross-table/period-overview', name: 'api_finances_cross_table_period_overview', methods: ['GET'])]
    public function getCrossTablePeriodOverview(Request $request): JsonResponse
    {
        $from = (string) $request->query->get('from', '');
        $to = (string) $request->query->get('to', '');
        if ($from === '' || $to === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            return $this->json(['error' => 'Les paramètres from et to (YYYY-MM-DD) sont requis.'], 400);
        }

        try {
            return $this->json($this->financeService->getCrossTablePeriodOverview($from, $to));
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        }
    }

    #[Route('/api/finances/fixed-charges', name: 'api_finances_fixed_charges_list', methods: ['GET'])]
    public function listFixedCharges(): JsonResponse
    {
        return $this->json([
            'items' => $this->financeService->listFixedCharges(),
            'total' => $this->financeService->getFixedChargesTotal(),
        ]);
    }

    #[Route('/api/finances/fixed-charges', name: 'api_finances_fixed_charges_create', methods: ['POST'])]
    public function createFixedCharge(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $result = $this->financeService->createFixedCharge($data);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result, 201);
    }

    #[Route('/api/finances/fixed-charges/{id}', name: 'api_finances_fixed_charges_update', methods: ['PUT'])]
    public function updateFixedCharge(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $result = $this->financeService->updateFixedCharge($id, $data);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/finances/fixed-charges/{id}', name: 'api_finances_fixed_charges_delete', methods: ['DELETE'])]
    public function deleteFixedCharge(int $id): JsonResponse
    {
        $result = $this->financeService->deleteFixedCharge($id);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

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

    private function applyMethodPayload(ModeDePaiement $method, array $data, bool $setLibelle = true): void
    {
        if ($setLibelle && !empty($data['libelle'])) {
            $method->setLibelle((string) $data['libelle']);
        }

        $type = $this->normalizeType($data['type'] ?? null);
        $method->setType($type ?? 'cash');
        $method->setCoverageRate(null);

        if (array_key_exists('notes', $data)) {
            $method->setNotes($data['notes']);
        }
    }

    private function normalizeType(?string $type): ?string
    {
        $candidate = strtolower(trim((string) ($type ?? '')));
        if ($candidate === '') {
            return null;
        }

        $candidate = str_replace([' ', '-', '_'], '', $candidate);
        $candidate = str_replace(['è', 'é', 'ê', 'ë', 'à', 'â', 'î', 'ï', 'ô', 'ù', 'û', 'ç'], ['e', 'e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'u', 'u', 'c'], $candidate);

        return match (true) {
            str_contains($candidate, 'mobile') && str_contains($candidate, 'money') => 'mobilemoney',
            str_contains($candidate, 'vir') || str_contains($candidate, 'transfer') => 'transfer',
            str_contains($candidate, 'carte') || str_contains($candidate, 'card') || str_contains($candidate, 'cb') => 'card',
            str_contains($candidate, 'esp') || str_contains($candidate, 'cash') || str_contains($candidate, 'liqu') => 'cash',
            default => null,
        };
    }

    private function isInsurancePayload(array $data, ?ModeDePaiement $currentMethod = null): bool
    {
        $rawType = strtolower(trim((string) ($data['type'] ?? $currentMethod?->getType() ?? '')));
        $rawLabel = strtolower(trim((string) ($data['nom'] ?? $data['libelle'] ?? $currentMethod?->getLibelle() ?? '')));

        return str_contains($rawType, 'insur')
            || str_contains($rawType, 'assur')
            || str_contains($rawLabel, 'insur')
            || str_contains($rawLabel, 'assur');
    }
}