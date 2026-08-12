<?php

namespace App\Billing\Controller\Api;

use App\Billing\Application\Command\CreateFixedCharge\CreateFixedChargeCommand;
use App\Billing\Application\Command\CreatePaymentMethod\CreatePaymentMethodCommand;
use App\Billing\Application\Command\DeleteFixedCharge\DeleteFixedChargeCommand;
use App\Billing\Application\Command\DeletePaymentMethod\DeletePaymentMethodCommand;
use App\Billing\Application\Command\TogglePaymentMethod\TogglePaymentMethodCommand;
use App\Billing\Application\Command\UpdateFixedCharge\UpdateFixedChargeCommand;
use App\Billing\Application\Command\UpdatePaymentMethod\UpdatePaymentMethodCommand;
use App\Billing\Application\Query\GetFinanceChartData\GetFinanceChartDataQuery;
use App\Billing\Application\Query\GetFinanceCrossTable\GetFinanceCrossTableQuery;
use App\Billing\Application\Query\GetFinanceCrossTableDayOverview\GetFinanceCrossTableDayOverviewQuery;
use App\Billing\Application\Query\ListFixedCharges\ListFixedChargesQuery;
use App\Billing\Application\Query\ListPaymentMethods\ListPaymentMethodsQuery;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class PaymentMethodController extends AbstractController
{
    public function __construct(
        private QueryBus $queryBus,
        private CommandBus $commandBus,
    ) {
    }

    #[Route('/api/payment-methods', name: 'api_modes_paiement_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListPaymentMethodsQuery()));
    }

    #[Route('/api/payment-methods', name: 'api_modes_paiement_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $result = $this->commandBus->dispatch(new CreatePaymentMethodCommand(is_array($data) ? $data : []));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result, 201);
    }

    #[Route('/api/payment-methods/{id}', name: 'api_modes_paiement_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $result = $this->commandBus->dispatch(new UpdatePaymentMethodCommand($id, $data));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/payment-methods/{id}', name: 'api_modes_paiement_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $result = $this->commandBus->dispatch(new DeletePaymentMethodCommand($id));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/payment-methods/{id}/toggle', name: 'api_modes_paiement_toggle', methods: ['PATCH'])]
    public function toggle(int $id): JsonResponse
    {
        $result = $this->commandBus->dispatch(new TogglePaymentMethodCommand($id));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/finances/chart-data', name: 'api_finances_chart_data', methods: ['GET'])]
    public function getChartData(Request $request): JsonResponse
    {
        $selectedYear = $request->query->getInt('year', (int) date('Y'));

        return $this->json($this->queryBus->ask(new GetFinanceChartDataQuery($selectedYear)));
    }

    #[Route('/api/finances/cross-table', name: 'api_finances_cross_table', methods: ['GET'])]
    public function getCrossTable(Request $request): JsonResponse
    {
        $year = $request->query->getInt('year', (int) date('Y'));
        $month = $request->query->getInt('month', (int) date('n'));
        $type = (string) $request->query->get('type', 'revenue');

        if ($month < 1 || $month > 12) {
            return $this->json(['error' => 'Le mois doit être compris entre 1 et 12.'], 400);
        }

        return $this->json($this->queryBus->ask(new GetFinanceCrossTableQuery($year, $month, $type)));
    }

    #[Route('/api/finances/cross-table/day-overview', name: 'api_finances_cross_table_day_overview', methods: ['GET'])]
    public function getCrossTableDayOverview(Request $request): JsonResponse
    {
        $date = (string) $request->query->get('date', '');
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->json(['error' => 'Le paramètre date (YYYY-MM-DD) est requis.'], 400);
        }

        try {
            return $this->json($this->queryBus->ask(new GetFinanceCrossTableDayOverviewQuery($date)));
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        }
    }

    #[Route('/api/finances/fixed-charges', name: 'api_finances_fixed_charges_list', methods: ['GET'])]
    public function listFixedCharges(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListFixedChargesQuery()));
    }

    #[Route('/api/finances/fixed-charges', name: 'api_finances_fixed_charges_create', methods: ['POST'])]
    public function createFixedCharge(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $result = $this->commandBus->dispatch(new CreateFixedChargeCommand($data));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result, 201);
    }

    #[Route('/api/finances/fixed-charges/{id}', name: 'api_finances_fixed_charges_update', methods: ['PUT'])]
    public function updateFixedCharge(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $result = $this->commandBus->dispatch(new UpdateFixedChargeCommand($id, $data));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/finances/fixed-charges/{id}', name: 'api_finances_fixed_charges_delete', methods: ['DELETE'])]
    public function deleteFixedCharge(int $id): JsonResponse
    {
        $result = $this->commandBus->dispatch(new DeleteFixedChargeCommand($id));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }
}
