<?php

namespace App\Billing\Controller\Api;

use App\Billing\Entity\Assurance;
use App\Billing\Repository\AssuranceRepository;
use App\Billing\Service\InsuranceClaimService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class InsuranceController extends AbstractController
{
    public function __construct(
        private AssuranceRepository $assuranceRepository,
        private InsuranceClaimService $insuranceClaimService,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/assurances', name: 'api_assurances_list', methods: ['GET'])]
    public function listAssurances(): JsonResponse
    {
        $assurances = $this->assuranceRepository->findBy([], ['nom' => 'ASC']);

        return $this->json(array_map(fn (Assurance $assurance) => $this->mapAssurance($assurance), $assurances));
    }

    #[Route('/api/assurances', name: 'api_assurances_create', methods: ['POST'])]
    public function createAssurance(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $nom = trim((string) ($data['nom'] ?? ''));
        $defaultRateRaw = $data['defaultRate'] ?? $data['default_rate'] ?? $data['taux_par_defaut'] ?? null;
        $defaultRate = $defaultRateRaw === null || $defaultRateRaw === ''
            ? null
            : max(0, min(100, (float) $defaultRateRaw));

        if ($nom === '') {
            return $this->json(['error' => 'Le nom de l\'assurance est requis.'], 400);
        }

        $assurance = new Assurance();
        $assurance->setNom($nom);
        $assurance->setCode(isset($data['code']) ? trim((string) $data['code']) : null);
        $assurance->setNotes(isset($data['notes']) ? trim((string) $data['notes']) : null);
        $assurance->setTauxParDefaut($defaultRate);
        $assurance->setActif(isset($data['actif']) ? (bool) $data['actif'] : true);

        $this->em->persist($assurance);
        $this->em->flush();

        return $this->json($this->mapAssurance($assurance), 201);
    }

    #[Route('/api/assurances/claims', name: 'api_insurance_claims_list', methods: ['GET'])]
    public function listClaims(Request $request): JsonResponse
    {
        $status = $request->query->get('status');

        return $this->json([
            'data' => $this->insuranceClaimService->listClaims(is_string($status) ? $status : null),
        ]);
    }

    #[Route('/api/assurances/claims/{id}/validate', name: 'api_insurance_claims_validate', methods: ['PATCH'])]
    public function validateClaim(int $id): JsonResponse
    {
        $result = $this->insuranceClaimService->validateClaim($id);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/claims/{id}/reject', name: 'api_insurance_claims_reject', methods: ['PATCH'])]
    public function rejectClaim(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?: [];
        $reason = isset($payload['reason']) ? (string) $payload['reason'] : null;

        $result = $this->insuranceClaimService->rejectClaim($id, $reason);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/claims/{id}/recover', name: 'api_insurance_claims_recover', methods: ['POST'])]
    public function recoverClaim(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?: [];
        $modeId = (int) ($payload['modeId'] ?? 0);

        $date = null;
        if (!empty($payload['date'])) {
            try {
                $date = new \DateTimeImmutable((string) $payload['date']);
            } catch (\Exception) {
                return $this->json(['error' => 'Date invalide'], 400);
            }
        }

        $result = $this->insuranceClaimService->recoverClaim($id, $modeId, $date);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result, 201);
    }

    private function mapAssurance(Assurance $assurance): array
    {
        return [
            'id' => $assurance->getId(),
            'nom' => $assurance->getNom(),
            'code' => $assurance->getCode(),
            'actif' => $assurance->isActif(),
            'notes' => $assurance->getNotes(),
            'defaultRate' => $assurance->getTauxParDefaut(),
            'tauxParDefaut' => $assurance->getTauxParDefaut(),
        ];
    }
}
