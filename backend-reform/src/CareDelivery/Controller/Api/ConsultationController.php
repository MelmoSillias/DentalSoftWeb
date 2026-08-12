<?php

namespace App\CareDelivery\Controller\Api;

use App\CareDelivery\Application\Command\DeleteConsultation\DeleteConsultationCommand;
use App\CareDelivery\Application\Command\LinkOrCreateFiche\LinkOrCreateFicheCommand;
use App\CareDelivery\Application\Command\UpdateFactureLines\UpdateFactureLinesCommand;
use App\CareDelivery\Application\Command\UpdateOrdonnance\UpdateOrdonnanceCommand;
use App\CareDelivery\Application\Command\VerifyConsultationMedecinPassword\VerifyConsultationMedecinPasswordCommand;
use App\CareDelivery\Application\Query\GetConsultationDetails\GetConsultationDetailsQuery;
use App\CareDelivery\Application\Query\GetFactureLines\GetFactureLinesQuery;
use App\CareDelivery\Application\Query\GetOrdonnance\GetOrdonnanceQuery;
use App\CareDelivery\Application\Query\ListConsultations\ListConsultationsQuery;
use App\CareDelivery\Application\Query\ListOrdonnances\ListOrdonnancesQuery;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\Settings\Service\GlobalSettingsService;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ConsultationController extends AbstractController{

    public function __construct(
        private GlobalSettingsService $globalSettingsService,
        private QueryBus $queryBus,
        private CommandBus $commandBus,
    )
    {
    }

    #[Route('/api/consultation/set_fiche/{ficheId}', name: 'api_consultation_set_fiche', methods: ['POST'], defaults: ['ficheId' => null])]
    public function setFiche(Request $request, ?int $ficheId = null): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $consultationId = $data['consultationId'] ?? $request->get('consultationId');
        $ficheId = $ficheId ?? ($data['ficheId'] ?? null);

        if (!$consultationId) {
            return $this->json(['error' => 'consultationId requis'], 400);
        }

        $ficheId = $ficheId !== null ? (int) $ficheId : null;
        $forceCreate = (bool) ($data['createNew'] ?? $data['forceCreate'] ?? false);
        $allowDuplicate = (bool) ($data['allowDuplicate'] ?? false);

        try {
            $restrictToMedecin = $this->isGranted('ROLE_MEDECIN') && !$this->isGranted('ROLE_ADMIN');
            $result = $this->commandBus->dispatch(new LinkOrCreateFicheCommand(
                (int) $consultationId,
                $ficheId,
                $this->getUser(),
                $restrictToMedecin,
                $forceCreate,
                $allowDuplicate,
            ));
        } catch (NotFoundHttpException $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        } catch (ConflictHttpException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json($result);
    }

    #[Route('/api/consultations/pending', name: 'consultations_pending')]
    public function pendingConsultations(): Response
    {
        $restrictToMedecin = $this->isGranted('ROLE_MEDECIN') && !$this->isGranted('ROLE_ADMIN');
        $data = $this->queryBus->ask(new ListConsultationsQuery(
            ListConsultationsQuery::SCOPE_PENDING,
            $this->getUser(),
            $restrictToMedecin,
        ));

        return $this->json(
            $data
        );
    }

    #[Route('/api/consultations/closed', name: 'api_consultations_closed', methods: ['GET'])]
    public function getClosedConsultations(): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new ListConsultationsQuery(
            ListConsultationsQuery::SCOPE_CLOSED,
        )));
    } 

    #[Route('/api/consultations/day', name: 'api_consultations_day', methods: ['GET'])]
    public function getConsultationsDay(Request $req): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new ListConsultationsQuery(
            ListConsultationsQuery::SCOPE_DAY,
            $this->getUser(),
            false,
            $req->get('date'),
        )));
    }

    #[Route('/api/focus/reception', name: 'api_focus_reception', methods: ['GET'])]
    public function getReceptionFocusData(Request $req): JsonResponse
    {
        return $this->json(
            $this->queryBus->ask(new ListConsultationsQuery(
                ListConsultationsQuery::SCOPE_RECEPTION_FOCUS,
                $this->getUser(),
                false,
                $req->get('date'),
            ))
        );
    }

    #[Route('/api/consultations/{id}', name: 'api_consultation_delete', methods: ['DELETE'])]
    public function deleteConsultation(int $id): JsonResponse
    {
        $user = $this->getUser();
        $deleted = $this->commandBus->dispatch(new DeleteConsultationCommand(
            $id,
            $user instanceof User ? $user : null,
        ));
 
        if (!$deleted) {
            return $this->json(['error' => 'Consultation introuvable'], 404);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/api/consultations/{consultation}/ordonnances', name: 'api_consultation_ordonnances', methods: ['GET'])]
    public function listOrdonnances(Consultation $consultation): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new ListOrdonnancesQuery(
            (int) $consultation->getId(),
        )));
    }

    #[Route('/api/consultations/{consultation}/ordonnances', name: 'api_consultation_ordonnance_add', methods: ['POST'])]
    public function addOrdonnance(Request $request, Consultation $consultation): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'deprecated' => true,
            'message' => "Endpoint déprécié: sauvegardez l'ordonnance via l'endpoint de mise à jour consultation (/api/fiches/{ficheId}/consultations/{consultationId}).",
        ], 410);
    }

    #[Route('/api/ordonnance/{id}', name: 'api_ordonnance_get', methods: ['GET'])]
    public function getOrdonnance(int $id): JsonResponse
    {
        $data = $this->queryBus->ask(new GetOrdonnanceQuery($id));
        if (!$data) {
            return new JsonResponse(['error' => 'Ordonnance introuvable'], 404);
        }
        return new JsonResponse($data);
    }

    #[Route('/api/ordonnance/{id}', name: 'api_ordonnance_update', methods: ['PUT'])]
    public function updateOrdonnance(Request $request, int $id): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Payload invalide'], 400);
        }

        try {
            $data = $this->commandBus->dispatch(new UpdateOrdonnanceCommand($id, $payload));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }

        if (!$data) {
            return new JsonResponse(['error' => 'Ordonnance introuvable'], 404);
        }

        return new JsonResponse($data);
    }

    #[Route('/api/ordonnance/{id}/print', name: 'api_ordonnance_print', methods: ['GET'])]
    public function printOrdonnance(int $id): Response
    {
        $data = $this->queryBus->ask(new GetOrdonnanceQuery($id));
        if (!$data) {
            return new Response('Ordonnance introuvable', 404);
        }

        $html = $this->renderView('ordonnance/print.html.twig', [
            'data' => $data,
        ]);

        return new Response($html);
    }

    #[Route('/api/prints/ordonnances/{id}', name: 'api_print_ordonnance_data', methods: ['GET'])]
    public function getOrdonnancePrintData(int $id): JsonResponse
    {
        $data = $this->queryBus->ask(new GetOrdonnanceQuery($id));
        if (!$data) {
            return new JsonResponse(['error' => 'Ordonnance introuvable'], 404);
        }

        return new JsonResponse([
            'data' => $data,
        ]);
    }

    #[Route('/api/consultations/{consultation}/facture', name: 'api_consultation_facture', methods: ['GET'])]
    public function getFactureLines(Consultation $consultation): JsonResponse
    {
        $lignes = $this->queryBus->ask(new GetFactureLinesQuery((int) $consultation->getId()));

        if ($lignes === null) {
            return new JsonResponse(['error' => 'Facture non trouvée'], 404);
        }

        return new JsonResponse($lignes);
    }

    #[Route('/api/consultations/{consultation}/facture/update', name: 'api_consultation_facture_update', methods: ['PUT'])]
    public function updateFactureLines(Request $request, Consultation $consultation): JsonResponse
    {
        if (!$this->canModifyConsultationInvoice()) {
            return new JsonResponse(['error' => 'Modification de facture non autorisée pour votre profil.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['lignes'])) {
            return new JsonResponse(['error' => 'Payload invalide'], 400);
        }

        $result = $this->commandBus->dispatch(new UpdateFactureLinesCommand(
            (int) $consultation->getId(),
            $data['lignes'],
            $data['date'] ?? $data['dateFacture'] ?? null,
            $data['time'] ?? $data['timeFacture'] ?? null,
        ));

        return new JsonResponse($result, isset($result['error']) ? 400 : 200);
    }

    private function canModifyConsultationInvoice(): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $isSecretary = $this->isGranted('ROLE_RECEPTION')
            || $this->isGranted('ROLE_RECEPTIONNISTE')
            || $this->isGranted('ROLE_SECRETAIRE');

        return $isSecretary && $this->globalSettingsService->isReceptionInvoiceModificationAllowed();
    }

    #[Route('/api/admin/consultation/{id}/details', name: 'api_consultation_details', methods: ['GET'])]
    public function getConsultationDetailsJson(int $id): JsonResponse
    {
        $context = $this->queryBus->ask(new GetConsultationDetailsQuery(
            $id,
            GetConsultationDetailsQuery::MODE_CONTEXT,
        ));

        return $this->json([
            'consultation' => $context['consultation'],
            'actes' => $context['actes'],
        ]);
    }

    #[Route('/api/consultations/{id}/details', name: 'api_consultation_details_public', methods: ['GET'])]
    public function getConsultationDetailsPublic(int $id): JsonResponse
    {
        $details = $this->queryBus->ask(new GetConsultationDetailsQuery(
            $id,
            GetConsultationDetailsQuery::MODE_DATA,
        ));

        return $this->json($details['data']);
    }

    #[Route('/api/consultations/{id}/verify-medecin-password', name: 'api_consultation_verify_medecin_password', methods: ['POST'])]
    public function verifyMedecinPassword(Request $request, int $id): JsonResponse
    {
        if (!$this->globalSettingsService->isReceptionQuickCloseConsultationAllowed()) {
            return $this->json(['error' => 'La clôturation rapide est désactivée.'], 403);
        }

        if (!$this->isGranted('ROLE_RECEPTION') && !$this->isGranted('ROLE_RECEPTIONNISTE')) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        if ($this->globalSettingsService->canReceptionBypassMedecinPasswordOnQuickClose()) {
            return $this->json(['valid' => true, 'bypass' => true]);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $password = (string) ($data['password'] ?? '');

        $isValid = $this->commandBus->dispatch(new VerifyConsultationMedecinPasswordCommand($id, $password));

        return $this->json(['valid' => $isValid]);
    } 
}
