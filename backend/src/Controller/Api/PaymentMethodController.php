<?php

namespace App\Controller\Api;

use App\Repository\ModeDePaiementRepository;
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
        private \App\Service\FinanceService $financeService
    ) {}

    #[Route('/api/payment-methods', name: 'api_modes_paiement_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $methods = $this->paymentMethodRepo->findAll();
        $data = array_map(fn($method) => [
            'id' => $method->getId(),
            'libelle' => $method->getLibelle(),
            'type' => $method->getType(),
            'actif' => $method->isActif(),
            'notes' => $method->getNotes(),
        ], $methods);

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

        $method = new \App\Entity\ModeDePaiement();
        $method->setLibelle($libelle);
        $method->setType($data['type'] ?? 'Autre');
        $method->setNotes($data['notes'] ?? null);
        $method->setActif(true);

        $this->em->persist($method);
        $this->em->flush();

        return $this->json([
            'id' => $method->getId(),
            'libelle' => $method->getLibelle(),
            'type' => $method->getType(),
            'actif' => $method->isActif(),
            'notes' => $method->getNotes(),
        ], 201);
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

        if ($libelle) {
            $method->setLibelle($libelle);
        }
        if (array_key_exists('type', $data) && $data['type']) {
            $method->setType($data['type']);
        }
        if (array_key_exists('notes', $data)) {
            $method->setNotes($data['notes']);
        }
        if (array_key_exists('actif', $data)) {
            $method->setActif((bool) $data['actif']);
        }

        $this->em->flush();

        return $this->json([
            'id' => $method->getId(),
            'libelle' => $method->getLibelle(),
            'type' => $method->getType(),
            'actif' => $method->isActif(),
            'notes' => $method->getNotes(),
        ]);
    }

    #[Route('/api/payment-methods/{id}', name: 'api_modes_paiement_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $method = $this->paymentMethodRepo->find($id);
        if (!$method) {
            return $this->json(['error' => 'Mode de paiement non trouvé'], 404);
        }

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

        return $this->json([
            'id' => $method->getId(),
            'libelle' => $method->getLibelle(),
            'type' => $method->getType(),
            'actif' => $method->isActif(),
            'notes' => $method->getNotes(),
        ]);
    }

    #[Route('/api/finances/chart-data', name: 'api_finances_chart_data', methods: ['GET'])]
    public function getChartData(): JsonResponse
    {
        $transactions = $this->financeService->getTransactions();
        $monthly = $this->financeService->computeMonthlySummary($transactions);

        return $this->json([
            'months' => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
            'datasetsComptes' => $this->financeService->getGraphDatasetsParCompteComplet(),
            'barSoldeChart' => $this->financeService->getBarPointChartData(),
            'evolutionCapital' => $this->financeService->getEvolutionCapitalAnnuel(),
        ]);
    }
}