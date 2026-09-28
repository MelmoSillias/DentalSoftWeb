<?php

namespace App\Billing\Controller\Api;

use App\Billing\Service\CabinetServiceBillingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class CabinetServiceController extends AbstractController
{
    public function __construct(
        private CabinetServiceBillingService $cabinetServices,
    ) {
    }

    #[Route('/api/patients/{id}/services-cabinet', name: 'api_patient_services_cabinet_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function listForPatient(int $id): JsonResponse
    {
        return new JsonResponse(['data' => $this->cabinetServices->listForPatient($id)]);
    }

    #[Route('/api/patients/{id}/services-cabinet', name: 'api_patient_services_cabinet_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function create(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $result = $this->cabinetServices->createForPatient($id, $payload);
        $status = (int) ($result['status'] ?? ($result['success'] ?? false ? 201 : 400));

        return new JsonResponse($result, $status);
    }

    #[Route('/api/services-cabinet/{id}/cancel', name: 'api_services_cabinet_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(int $id): JsonResponse
    {
        $result = $this->cabinetServices->cancel($id);
        $status = (int) ($result['status'] ?? 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/factures-cabinet/{id}', name: 'api_factures_cabinet_preview', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function preview(int $id): JsonResponse
    {
        $row = $this->cabinetServices->previewFacture($id);
        if ($row === null) {
            return new JsonResponse(['error' => 'Facture cabinet introuvable'], 404);
        }

        return new JsonResponse($row);
    }

    #[Route('/api/factures-cabinet/{id}/pay', name: 'api_factures_cabinet_pay', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function pay(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $result = $this->cabinetServices->payFacture($id, $payload);
        $status = (int) ($result['status'] ?? 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/prints/factures-cabinet/{id}', name: 'api_print_facture_cabinet', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function printFacture(int $id): JsonResponse
    {
        $row = $this->cabinetServices->previewFacture($id);
        if ($row === null) {
            return new JsonResponse(['error' => 'Facture cabinet introuvable'], 404);
        }

        return new JsonResponse([
            'doc' => $row,
            'title' => 'Facture service cabinet',
        ]);
    }
}
