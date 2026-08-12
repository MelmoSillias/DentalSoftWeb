<?php

namespace App\Billing\Infrastructure\Adapter;

use App\Billing\Application\Port\FinanceWritePort;
use App\Billing\Infrastructure\Persistence\Doctrine\Entity\ModeDePaiement;
use App\Billing\Infrastructure\Persistence\Doctrine\Repository\ModeDePaiementRepository;
use App\Billing\Service\FinanceService;
use App\Focus\Service\FocusRealtimePublisher;
use Doctrine\ORM\EntityManagerInterface;

final class LegacyFinanceWriteAdapter implements FinanceWritePort
{
    public function __construct(
        private readonly FinanceService $financeService,
        private readonly ModeDePaiementRepository $paymentMethodRepo,
        private readonly EntityManagerInterface $em,
        private readonly FocusRealtimePublisher $focusRealtimePublisher,
    ) {
    }

    public function createPaymentMethod(array $data): array
    {
        $libelle = $data['nom'] ?? $data['libelle'] ?? null;
        if (!$data || !$libelle) {
            return ['error' => 'Nom requis', 'status' => 400];
        }

        if ($this->isInsurancePayload($data)) {
            return ['error' => 'Les assurances ne sont plus gerées dans les modes de paiement.', 'status' => 400];
        }

        $method = new ModeDePaiement();
        $this->applyMethodPayload($method, $data);
        $method->setActif(true);

        $this->em->persist($method);
        $this->em->flush();
        $this->focusRealtimePublisher->publishPaymentMethodRefresh($method, 'created');

        return $this->mapMethod($method);
    }

    public function updatePaymentMethod(int $id, array $data): array
    {
        $method = $this->paymentMethodRepo->find($id);
        if (!$method) {
            return ['error' => 'Mode de paiement non trouvé', 'status' => 404];
        }

        $libelle = $data['nom'] ?? $data['libelle'] ?? null;

        if ($this->isInsurancePayload($data, $method)) {
            return ['error' => 'Les assurances ne sont plus gerées dans les modes de paiement.', 'status' => 400];
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

        return $this->mapMethod($method);
    }

    public function deletePaymentMethod(int $id): array
    {
        $method = $this->paymentMethodRepo->find($id);
        if (!$method) {
            return ['error' => 'Mode de paiement non trouvé', 'status' => 404];
        }

        $this->focusRealtimePublisher->publishPaymentMethodRefresh($method, 'deleted');
        $this->em->remove($method);
        $this->em->flush();

        return ['success' => true];
    }

    public function togglePaymentMethod(int $id): array
    {
        $method = $this->paymentMethodRepo->find($id);
        if (!$method) {
            return ['error' => 'Mode de paiement non trouvé', 'status' => 404];
        }

        $method->setActif(!$method->isActif());
        $this->em->flush();
        $this->focusRealtimePublisher->publishPaymentMethodRefresh($method, 'toggled');

        return $this->mapMethod($method);
    }

    public function createFixedCharge(array $data): array
    {
        return $this->financeService->createFixedCharge($data);
    }

    public function updateFixedCharge(int $id, array $data): array
    {
        return $this->financeService->updateFixedCharge($id, $data);
    }

    public function deleteFixedCharge(int $id): array
    {
        return $this->financeService->deleteFixedCharge($id);
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

    /**
     * @param array<string, mixed> $data
     */
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

    /**
     * @param array<string, mixed> $data
     */
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
