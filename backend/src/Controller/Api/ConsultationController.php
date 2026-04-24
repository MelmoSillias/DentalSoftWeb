<?php

namespace App\Controller\Api;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\ConsultationService;
use App\Service\GlobalSettingsService;
use App\Entity\Consultation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ConsultationController extends AbstractController{

    public function __construct(
        private ConsultationService $consultationService,
        private GlobalSettingsService $globalSettingsService,
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

        try {
            $restrictToMedecin = $this->isGranted('ROLE_MEDECIN') && !$this->isGranted('ROLE_ADMIN');
            $result = $this->consultationService->linkOrCreateFiche(
                (int) $consultationId,
                $ficheId,
                $this->getUser(),
                $restrictToMedecin,
            );
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
        $data = $this->consultationService->listPendingConsultationsJsonForUser($this->getUser(), $restrictToMedecin);

        return $this->json(
            $data
        );
    }

    #[Route('/api/consultations/closed', name: 'api_consultations_closed', methods: ['GET'])]
    public function getClosedConsultations(): JsonResponse
    {
        return new JsonResponse($this->consultationService->getClosedConsultationsData());
    } 

    #[Route('/api/consultations/day', name: 'api_consultations_day', methods: ['GET'])]
    public function getConsultationsDay(Request $req): JsonResponse
    {
        return new JsonResponse($this->consultationService->ConsultationsDuJour($req->get('date'), $this->getUser()));
    }

    #[Route('/api/focus/reception', name: 'api_focus_reception', methods: ['GET'])]
    public function getReceptionFocusData(Request $req): JsonResponse
    {
        return $this->json(
            $this->consultationService->getReceptionFocusData($req->get('date'), $this->getUser())->toArray()
        );
    }

    #[Route('/api/consultations/{id}', name: 'api_consultation_delete', methods: ['DELETE'])]
    public function deleteConsultation(int $id): JsonResponse
    {
        $user = $this->getUser();
        $deleted = $this->consultationService->deleteConsultation(
            $id,
            $user instanceof User ? $user : null,
        );
 
        if (!$deleted) {
            return $this->json(['error' => 'Consultation introuvable'], 404);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/api/consultations/{consultation}/ordonnances', name: 'api_consultation_ordonnances', methods: ['GET'])]
    public function listOrdonnances(Consultation $consultation): JsonResponse
    {
        return new JsonResponse($this->consultationService->listOrdonnances($consultation));
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
        $data = $this->consultationService->getOrdonnanceData($id);
        if (!$data) {
            return new JsonResponse(['error' => 'Ordonnance introuvable'], 404);
        }
        return new JsonResponse($data);
    }

    #[Route('/api/ordonnance/{id}/print', name: 'api_ordonnance_print', methods: ['GET'])]
    public function printOrdonnance(int $id): Response
    {
        $data = $this->consultationService->getOrdonnanceData($id);
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
        $data = $this->consultationService->getOrdonnanceData($id);
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
        $lignes = $this->consultationService->getFactureLines($consultation);

        if ($lignes === null) {
            return new JsonResponse(['error' => 'Facture non trouvée'], 404);
        }

        return new JsonResponse($lignes);
    }

    #[Route('/api/consultations/{consultation}/facture/update', name: 'api_consultation_facture_update', methods: ['PUT'])]
    public function updateFactureLines(Request $request, Consultation $consultation): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['lignes'])) {
            return new JsonResponse(['error' => 'Payload invalide'], 400);
        }

        $result = $this->consultationService->updateFactureLines($consultation, $data['lignes']);

        return new JsonResponse($result, isset($result['error']) ? 400 : 200);
    }

    
    #[Route('/api/admin/consultation/{id}/details', name: 'api_consultation_details', methods: ['GET'])]
    public function getConsultationDetailsJson(int $id): JsonResponse
    {
        $context = $this->consultationService->getConsultationDetailsContext($id);

        return $this->json([
            'consultation' => $context['consultation'],
            'actes' => $context['actes'],
        ]);
    }

    #[Route('/api/consultations/{id}/details', name: 'api_consultation_details_public', methods: ['GET'])]
    public function getConsultationDetailsPublic(int $id): JsonResponse
    {
        $details = $this->consultationService->getConsultationDetailsData($id);

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

        $data = json_decode($request->getContent(), true) ?? [];
        $password = (string) ($data['password'] ?? '');

        $isValid = $this->consultationService->verifyConsultationMedecinPassword($id, $password);

        return $this->json(['valid' => $isValid]);
    }

    
}