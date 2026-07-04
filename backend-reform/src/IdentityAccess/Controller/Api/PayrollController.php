<?php

namespace App\IdentityAccess\Controller\Api;

use App\IdentityAccess\Service\PayrollService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/payrolls')]
class PayrollController extends AbstractController
{
    public function __construct(private PayrollService $payrollService)
    {
    }

    #[Route('', name: 'api_payrolls_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $start = $request->query->getInt('start', 0);
            $length = $request->query->getInt('length', 10);
            $employeeId = $request->query->get('employeeId');
            $month = $request->query->get('month');
            $year = $request->query->get('year');

            $result = $this->payrollService->listPayrolls(
                $start,
                $length,
                $employeeId !== null && $employeeId !== '' ? (int) $employeeId : null,
                $month !== null && $month !== '' ? (int) $month : null,
                $year !== null && $year !== '' ? (int) $year : null,
            );

            return $this->json([
                'draw' => $request->query->getInt('draw', 1),
                'recordsTotal' => $result['total'],
                'recordsFiltered' => $result['filtered'],
                'data' => $result['data'],
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], 400);
        }
    }

    #[Route('/context/{employeeId}', name: 'api_payrolls_context', methods: ['GET'])]
    public function context(int $employeeId, Request $request): JsonResponse
    {
        try {
            $month = $request->query->getInt('month', (int) date('n'));
            $year = $request->query->getInt('year', (int) date('Y'));
            $day = $request->query->get('day');

            return $this->json($this->payrollService->getPaymentContext(
                $employeeId,
                $month,
                $year,
                is_string($day) && $day !== '' ? $day : null
            ));
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], 400);
        }
    }

    #[Route('', name: 'api_payrolls_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true) ?? [];
            $result = $this->payrollService->createSalaryPayment($payload);

            return $this->json($result, 201);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], 400);
        } catch (\Throwable $exception) {
            return $this->json(['message' => 'Une erreur inattendue est survenue.'], 500);
        }
    }

    #[Route('/payment-methods', name: 'api_payrolls_payment_methods', methods: ['GET'])]
    public function paymentMethods(): JsonResponse
    {
        return $this->json($this->payrollService->listActivePaymentMethods());
    }

    #[Route('/{id}/print', name: 'api_payrolls_print', methods: ['GET'])]
    public function printData(int $id): JsonResponse
    {
        try {
            return $this->json($this->payrollService->getPrintPayload($id));
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], 404);
        }
    }

    #[Route('/{id}', name: 'api_payrolls_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        try {
            return $this->json($this->payrollService->getSalaryPayment($id));
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], 404);
        }
    }

    #[Route('/{id}', name: 'api_payrolls_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true) ?? [];

            return $this->json($this->payrollService->updateSalaryPayment($id, $payload));
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], 400);
        }
    }

    #[Route('/{id}', name: 'api_payrolls_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            return $this->json($this->payrollService->deleteSalaryPayment($id));
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], 404);
        }
    }
}
