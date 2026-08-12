<?php

namespace App\ClinicalRecord\Controller\Api;

use App\ClinicalRecord\Application\Command\UpdateFicheBilans\UpdateFicheBilansCommand;
use App\ClinicalRecord\Application\Command\UpdateFicheDevis\UpdateFicheDevisCommand;
use App\ClinicalRecord\Application\Command\UpdateFicheDocuments\UpdateFicheDocumentsCommand;
use App\ClinicalRecord\Application\Command\UpdateFicheEntretien\UpdateFicheEntretienCommand;
use App\ClinicalRecord\Application\Command\UpdateFicheExamens\UpdateFicheExamensCommand;
use App\ClinicalRecord\Application\Command\UpdateFichePlanTraitement\UpdateFichePlanTraitementCommand;
use App\ClinicalRecord\Application\Query\GetFicheMedicale\GetFicheMedicaleQuery;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/fiches-medicales/{ficheId}', name: 'api_fiche_medicale_')]
class FicheMedicaleController extends AbstractController
{
    public function __construct(
        private QueryBus $queryBus,
        private CommandBus $commandBus,
    ) {
    }

    #[Route('/json', methods: ['GET'], name: 'json')]
    public function getJson(int $ficheId): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetFicheMedicaleQuery($ficheId)));
    }

    #[Route('/entretien', methods: ['POST'], name: 'update_entretien')]
    public function updateEntretien(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->commandBus->dispatch(new UpdateFicheEntretienCommand($ficheId, $data));

        return new JsonResponse(['success' => true]);
    }

    #[Route('/examens', methods: ['POST'], name: 'update_examens')]
    public function updateExamens(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->commandBus->dispatch(new UpdateFicheExamensCommand($ficheId, $data));

        return new JsonResponse(['success' => true]);
    }

    #[Route('/bilans', methods: ['POST'], name: 'update_bilans')]
    public function updateBilans(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->commandBus->dispatch(new UpdateFicheBilansCommand($ficheId, $data));

        return new JsonResponse(['success' => true]);
    }

    #[Route('/plan-traitement', methods: ['POST'], name: 'update_plan_traitement')]
    public function updatePlanTraitement(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->commandBus->dispatch(new UpdateFichePlanTraitementCommand($ficheId, $data));

        return new JsonResponse(['success' => true]);
    }

    #[Route('/documents', methods: ['POST'], name: 'update_documents')]
    public function updateDocuments(Request $request, int $ficheId): JsonResponse
    {
        $data = $request->get('data');
        $payload = $data ? json_decode($data, true) : json_decode($request->getContent(), true);
        $files = $request->files->get('documentsFiles', []);
        $this->commandBus->dispatch(new UpdateFicheDocumentsCommand($ficheId, $payload ?? [], $files));

        return new JsonResponse(['success' => true]);
    }

    #[Route('/devis', methods: ['POST'], name: 'update_devis')]
    public function updateDevis(Request $request, int $ficheId): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->commandBus->dispatch(new UpdateFicheDevisCommand($ficheId, $data));

        return new JsonResponse(['success' => true]);
    }
}
