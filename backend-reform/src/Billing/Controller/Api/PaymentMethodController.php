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
        $methods = $this->paymentMethodRepo->findAll();
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

        $validationError = $this->validateCoveragePayload($data);
        if ($validationError !== null) {
            return $this->json(['error' => $validationError], 400);
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

        $validationError = $this->validateCoveragePayload($data, $method);
        if ($validationError !== null) {
            return $this->json(['error' => $validationError], 400);
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
            'typeKey' => $method->getTypeKey(),
            'family' => $method->getFamilyKey(),
            'coverageRate' => $method->getCoverageRate(),
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

        $typeKey = $this->normalizeTypeKey($data['typeKey'] ?? null, $data['type'] ?? null);
        $familyKey = $this->normalizeFamilyKey($typeKey, $data['family'] ?? null);
        $typeLabel = $this->resolveTypeLabel($typeKey, $data['type'] ?? null);
        $coverageRate = $familyKey === 'insurance' && isset($data['coverageRate']) && $data['coverageRate'] !== null
            ? (float) $data['coverageRate']
            : null;

        $method->setType($typeLabel);
        $method->setTypeKey($typeKey);
        $method->setFamilyKey($familyKey);
        $method->setCoverageRate($coverageRate);

        if (array_key_exists('notes', $data)) {
            $method->setNotes($data['notes']);
        }
    }

    private function normalizeTypeKey(?string $typeKey, ?string $typeLabel): string
    {
        $candidate = strtolower(trim((string) ($typeKey ?: $typeLabel ?: 'other')));
        $candidate = str_replace([' ', '-'], '_', $candidate);
        $candidate = str_replace(['è', 'é', 'ê', 'ë', 'à', 'â', 'î', 'ï', 'ô', 'ù', 'û', 'ç'], ['e', 'e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'u', 'u', 'c'], $candidate);

        return match (true) {
            str_contains($candidate, 'mobile') && str_contains($candidate, 'money') => 'mobile_money',
            str_contains($candidate, 'assur') => 'insurance',
            str_contains($candidate, 'vir') || str_contains($candidate, 'transfer') => 'bank_transfer',
            str_contains($candidate, 'che') => 'cheque',
            str_contains($candidate, 'esp') || str_contains($candidate, 'cash') || str_contains($candidate, 'liqu') => 'cash',
            default => 'other',
        };
    }

    private function normalizeFamilyKey(string $typeKey, ?string $family): string
    {
        $candidate = strtolower(trim((string) $family));
        if ($candidate === 'insurance') {
            return 'insurance';
        }
        if ($candidate === 'classic') {
            return 'classic';
        }

        return $typeKey === 'insurance' ? 'insurance' : 'classic';
    }

    private function resolveTypeLabel(string $typeKey, ?string $fallback): string
    {
        if (!empty($fallback)) {
            return (string) $fallback;
        }

        return match ($typeKey) {
            'cash' => 'Espèces',
            'mobile_money' => 'Mobile Money',
            'cheque' => 'Chèque',
            'bank_transfer' => 'Virement bancaire',
            'insurance' => 'Assurance',
            default => 'Autre',
        };
    }

    private function validateCoveragePayload(array $data, ?ModeDePaiement $currentMethod = null): ?string
    {
        $typeKey = $this->normalizeTypeKey(
            $data['typeKey'] ?? $currentMethod?->getTypeKey(),
            $data['type'] ?? $currentMethod?->getType()
        );
        $familyKey = $this->normalizeFamilyKey(
            $typeKey,
            $data['family'] ?? $currentMethod?->getFamilyKey()
        );
        $coverageRate = $data['coverageRate'] ?? $currentMethod?->getCoverageRate();

        if ($familyKey === 'insurance' && (!is_numeric($coverageRate) || (float) $coverageRate <= 0)) {
            return 'Le pourcentage de prise en charge est obligatoire pour une assurance.';
        }

        return null;
    }
}