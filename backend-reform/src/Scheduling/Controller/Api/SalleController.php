<?php

namespace App\Scheduling\Controller\Api;

use App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\Salle;
use App\Scheduling\Service\SalleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Json;

class SalleController extends AbstractController
{
    public function __construct(
        private SalleService $salleService,
    ) {
    }

    #[Route('/api/salles', name: 'api_salles', methods: ['GET'])]
    public function getSalles(): JsonResponse
    {
        $salles = $this->salleService->list();

        $data = array_map(static function ($salle) {
            return [
                'id' => $salle->getId(),
                'nom' => $salle->getNom(),
                'description' => $salle->getDescription(),
            ];
        }, $salles);

        return new JsonResponse($data);
    }
  
    #[Route('/api/salles', name: 'api_salle_add', methods: ['POST'])]
    public function addSalle(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (!$data || empty($data['nom'])) {
                return new JsonResponse(['status' => 'error', 'message' => 'Le nom de la salle est requis.'], 400);
            }
            $salle = $this->salleService->add([
                'nom' => $data['nom'],
                'description' => $data['description'] ?? null,
            ]);
            return new JsonResponse([
                'status' => 'success',
                'message' => 'Salle ajoutée avec succès.',
                'salle' => [
                    'id' => $salle->getId(),
                    'nom' => $salle->getNom(),
                    'description' => $salle->getDescription(),
                ]
            ], 201);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Erreur lors de l\'ajout de la salle : ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/salles/{id}', name: 'api_salle_edit', methods: ['PUT'])]
    public function editSalle(Request $request, int $id): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (!$data || empty($data['nom'])) {
                return new JsonResponse(['status' => 'error', 'message' => 'Le nom de la salle est requis.'], 400);
            }
            $salle = $this->salleService->edit([
                'id' => $id,
                'nom' => $data['nom'],
                'description' => $data['description'] ?? null,
            ]);
            if (!$salle) {
                return new JsonResponse(['status' => 'error', 'message' => 'Salle non trouvée.'], 404);
            }
            return new JsonResponse([
                'status' => 'success',
                'message' => 'Salle modifiée avec succès.',
                'salle' => [
                    'id' => $salle->getId(),
                    'nom' => $salle->getNom(),
                    'description' => $salle->getDescription(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Erreur lors de la modification de la salle : ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/salles/{salle}', name: 'api_salle_delete', methods: ['DELETE'])]
    public function deleteSalle(Request $request, Salle $salle): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (!$data || empty($data['id'])) {
                return new JsonResponse(['status' => 'error', 'message' => 'L\'ID de la salle est requis.'], 400);
            } 

            if (!$salle) {
                return new JsonResponse(['status' => 'error', 'message' => 'Salle non trouvée.'], 404);
            }
            $this->salleService->delete($salle->getId());
            return new JsonResponse([
                'status' => 'success',
                'message' => 'Salle supprimée avec succès.',
                'salle' => [
                    'id' => $salle->getId(),
                    'nom' => $salle->getNom(),
                    'description' => $salle->getDescription(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Erreur lors de la suppression de la salle : ' . $e->getMessage()], 500);
        }
    }
}