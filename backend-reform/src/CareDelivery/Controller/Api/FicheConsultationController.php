<?php

namespace App\CareDelivery\Controller\Api;

use App\CareDelivery\Application\Command\ClotureConsultation\ClotureConsultationCommand;
use App\CareDelivery\Application\Command\UpdateConsultation\UpdateConsultationCommand;
use App\CareDelivery\Application\Query\GetConsultationJson\GetConsultationJsonQuery;
use App\ClinicalRecord\Service\FicheMedicaleService;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/fiches/{ficheId}', name: 'api_fiche_consultation_')]
class FicheConsultationController extends AbstractController
{
    public function __construct(
        private QueryBus $queryBus,
        private CommandBus $commandBus,
        private FicheMedicaleService $ficheMedicaleService,
    ) {}

    private function restrictToConnectedMedecin(): bool
    {
        return $this->isGranted('ROLE_MEDECIN') && !$this->isGranted('ROLE_ADMIN');
    }

    #[Route('/consultations/{consultationId}/json', name: 'json', methods: ['GET'])]
    public function getJson(int $ficheId, int $consultationId): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetConsultationJsonQuery(
            $ficheId,
            $consultationId,
            $this->getUser(),
            $this->restrictToConnectedMedecin(),
        )));
    }

    #[Route('/motif', methods: ['POST'], name: 'update_motif')]
    public function updateMotif(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? []; 
            $this->ficheMedicaleService->updateEntretien($ficheId, $data); 
        return new JsonResponse(['success' => true]);
    }

    #[Route('/examens', methods: ['POST'], name: 'update_examens')]
    public function updateExamens(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? []; 
 
            $this->ficheMedicaleService->updateExamens($ficheId, $data);
        return new JsonResponse(['success' => true]);
    }

    #[Route('/traitements', methods: ['POST'], name: 'update_traitements')]
    public function updateTraitements(Request $request, int $ficheId): JsonResponse
    {
        $data  = json_decode($request->get('data'), true) ?? [];
        $files = $request->files->get('documentsFiles', []); 
 
        $this->ficheMedicaleService->updateDocuments($ficheId, $data, $files ?: []); 
        return new JsonResponse(['success' => true]);
    }

    #[Route('/devis', methods: ['POST'], name: 'update_devis')]
    public function updateDevis(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? []; 
 
            $this->ficheMedicaleService->updateDevis($ficheId, $data); 

        return new JsonResponse(['success' => true]);
    }

    #[Route('/consultations/{consultationId}', methods: ['POST'], name: 'update')]
    public function update(Request $request, int $ficheId, int $consultationId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        try {
            $this->commandBus->dispatch(new UpdateConsultationCommand(
                $ficheId,
                $consultationId,
                $data,
                $this->getUser(),
                $this->restrictToConnectedMedecin(),
            ));
        } catch (ConflictHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
        return new JsonResponse(['success' => true]);
    }

    #[Route('/consultations/{consultationId}/cloture', methods: ['POST'], name: 'cloture')]
    public function close(Request $request, int $ficheId, int $consultationId): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        try {
            $this->commandBus->dispatch(new ClotureConsultationCommand(
                $ficheId,
                $consultationId,
                $this->getUser(),
                $this->restrictToConnectedMedecin(),
                $payload,
            ));
        } catch (ConflictHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
        return new JsonResponse(['success' => true]);
    }
}
