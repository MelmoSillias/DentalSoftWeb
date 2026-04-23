<?php

namespace App\Controller\Api;

use App\Service\AgendaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class EventController extends AbstractController
{
    public function __construct(
        private AgendaService $agendaService,
    ) {
    }

    private function jsonPayload(Request $request): array
    {
        $content = $request->getContent();
        if ($content) {
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data ?? [];
            }
        }

        return $request->request->all();
    }

    #[Route('/api/events', name: 'api_events_all', methods: ['GET'])]
    public function listEvents(): JsonResponse
    {
        return new JsonResponse($this->agendaService->listBookings());
    }

    #[Route('/api/events', name: 'api_event_create_booking', methods: ['POST'])]
    public function createBooking(Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $result = $this->agendaService->createBooking($payload);
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/events/{id}', name: 'api_event_delete', methods: ['DELETE'])]
    public function deleteBooking(int $id): JsonResponse
    {
        $result = $this->agendaService->deleteBooking($id);
        $status = $result['status'] ?? (isset($result['error']) ? 404 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/events/{id}/validate', name: 'api_event_validate', methods: ['POST'])]
    public function validateBooking(int $id): JsonResponse
    {
        $result = $this->agendaService->validateBooking($id);
        $status = $result['status'] ?? (isset($result['error']) ? 404 : 200);

        return new JsonResponse($result, $status);
    }
}