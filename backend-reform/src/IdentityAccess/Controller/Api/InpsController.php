<?php

namespace App\IdentityAccess\Controller\Api;

use App\IdentityAccess\Service\InpsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/inps')]
#[IsGranted('ROLE_ADMIN')]
class InpsController extends AbstractController
{
    public function __construct(private InpsService $service) {}
    private function respond(callable $operation): JsonResponse
    {
        try { return $this->json($operation()); }
        catch (\InvalidArgumentException $e) { return $this->json(['message' => $e->getMessage()], 400); }
    }
    #[Route('', methods: ['GET'])]
    public function overview(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->service->overview($request->query->getInt('year', (int) date('Y')), $request->query->getInt('quarter', (int) ceil(date('n') / 3))));
    }
    #[Route('/remittances', methods: ['POST'])]
    public function remit(Request $request): JsonResponse { return $this->respond(fn () => $this->service->remit($request->toArray())); }
    #[Route('/remittances/{id}/cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(int $id, Request $request): JsonResponse { return $this->respond(fn () => $this->service->cancel($id, $request->toArray())); }
    #[Route('/{year}/{quarter}/closure', methods: ['PUT'], requirements: ['year' => '\d{4}', 'quarter' => '[1-4]'])]
    public function closure(int $year, int $quarter, Request $request): JsonResponse { return $this->respond(fn () => $this->service->setClosed($year, $quarter, $request->toArray())); }
}
