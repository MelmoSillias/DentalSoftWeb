<?php

namespace App\Billing\Controller\Api;

use App\Billing\Application\Command\AddClaimToLot\AddClaimToLotCommand;
use App\Billing\Application\Command\CancelLotRecovery\CancelLotRecoveryCommand;
use App\Billing\Application\Command\CancelRefund\CancelRefundCommand;
use App\Billing\Application\Command\ConfirmLot\ConfirmLotCommand;
use App\Billing\Application\Command\MoveClaimToLot\MoveClaimToLotCommand;
use App\Billing\Application\Command\OpenLot\OpenLotCommand;
use App\Billing\Application\Command\RefundLot\RefundLotCommand;
use App\Billing\Application\Command\RemoveClaimFromLot\RemoveClaimFromLotCommand;
use App\Billing\Application\Command\ReopenLot\ReopenLotCommand;
use App\Billing\Application\Command\SendLot\SendLotCommand;
use App\Billing\Application\Command\UnconfirmLot\UnconfirmLotCommand;
use App\Billing\Application\Command\UpdateLot\UpdateLotCommand;
use App\Billing\Application\Query\GetAssuranceDashboard\GetAssuranceDashboardQuery;
use App\Billing\Application\Query\GetClaimDetail\GetClaimDetailQuery;
use App\Billing\Application\Query\GetLot\GetLotQuery;
use App\Billing\Application\Query\ListLots\ListLotsQuery;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class LotFactureAssuranceController extends AbstractController
{
    public function __construct(
        private QueryBus $queryBus,
        private CommandBus $commandBus,
    ) {
    }

    #[Route('/api/assurances/dashboard', name: 'api_assurances_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        return $this->json(['data' => $this->queryBus->ask(new GetAssuranceDashboardQuery())]);
    }

    #[Route('/api/assurances/{code}/lots', name: 'api_assurances_lots_list', methods: ['GET'])]
    public function listLots(string $code, Request $request): JsonResponse
    {
        $statut = $request->query->get('statut');
        $result = $this->queryBus->ask(new ListLotsQuery($code, is_string($statut) ? $statut : null));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/{code}/lots', name: 'api_assurances_lots_open', methods: ['POST'])]
    public function openLot(string $code, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?: [];

        $dateDebut = null;
        $dateFin = null;
        try {
            if (!empty($payload['dateDebut'])) {
                $dateDebut = new \DateTime((string) $payload['dateDebut']);
            }
            if (!empty($payload['dateFin'])) {
                $dateFin = new \DateTime((string) $payload['dateFin']);
            }
        } catch (\Exception) {
            return $this->json(['error' => 'Dates invalides'], 400);
        }

        $result = $this->commandBus->dispatch(new OpenLotCommand(
            $code,
            isset($payload['description']) ? (string) $payload['description'] : null,
            $dateDebut,
            $dateFin,
        ));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error'], 'lotId' => $result['lotId'] ?? null], $result['status'] ?? 400);
        }

        return $this->json($result, 201);
    }

    #[Route('/api/assurances/lots/{id}', name: 'api_assurances_lots_detail', methods: ['GET'])]
    public function getLot(int $id): JsonResponse
    {
        $result = $this->queryBus->ask(new GetLotQuery($id));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 404);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/lots/{id}', name: 'api_assurances_lots_update', methods: ['PATCH'])]
    public function updateLot(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?: [];

        $dateDebut = null;
        $dateFin = null;
        try {
            if (!empty($payload['dateDebut'])) {
                $dateDebut = new \DateTime((string) $payload['dateDebut']);
            }
            if (!empty($payload['dateFin'])) {
                $dateFin = new \DateTime((string) $payload['dateFin']);
            }
        } catch (\Exception) {
            return $this->json(['error' => 'Dates invalides'], 400);
        }

        $result = $this->commandBus->dispatch(new UpdateLotCommand(
            $id,
            array_key_exists('description', $payload) ? (string) $payload['description'] : null,
            $dateDebut,
            $dateFin,
        ));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/lots/{id}/send', name: 'api_assurances_lots_send', methods: ['POST'])]
    public function sendLot(int $id): JsonResponse
    {
        $result = $this->commandBus->dispatch(new SendLotCommand($id));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/lots/{id}/reopen', name: 'api_assurances_lots_reopen', methods: ['POST'])]
    public function reopenLot(int $id): JsonResponse
    {
        $result = $this->commandBus->dispatch(new ReopenLotCommand($id));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/lots/{id}/confirm', name: 'api_assurances_lots_confirm', methods: ['POST'])]
    public function confirmLot(int $id): JsonResponse
    {
        $result = $this->commandBus->dispatch(new ConfirmLotCommand($id));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/lots/{id}/unconfirm', name: 'api_assurances_lots_unconfirm', methods: ['POST'])]
    public function unconfirmLot(int $id): JsonResponse
    {
        $result = $this->commandBus->dispatch(new UnconfirmLotCommand($id));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/lots/{id}/refund', name: 'api_assurances_lots_refund', methods: ['POST'])]
    public function refundLot(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?: [];
        $modeId = (int) ($payload['modeId'] ?? 0);
        $amountRaw = $payload['amount'] ?? $payload['montant'] ?? null;
        $amount = $amountRaw === null || $amountRaw === '' ? null : (float) $amountRaw;

        $date = null;
        if (!empty($payload['date'])) {
            try {
                $date = new \DateTimeImmutable((string) $payload['date']);
            } catch (\Exception) {
                return $this->json(['error' => 'Date invalide'], 400);
            }
        }

        $result = $this->commandBus->dispatch(new RefundLotCommand($id, $modeId, $amount, $date));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result, 201);
    }

    /** @deprecated Prefer /refund */
    #[Route('/api/assurances/lots/{id}/recover', name: 'api_assurances_lots_recover', methods: ['POST'])]
    public function recoverLot(int $id, Request $request): JsonResponse
    {
        return $this->refundLot($id, $request);
    }

    #[Route('/api/assurances/lots/{id}/refunds/{transactionId}/cancel', name: 'api_assurances_lots_refund_cancel', methods: ['PATCH'])]
    public function cancelRefund(int $id, int $transactionId, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?: [];
        $comment = isset($payload['comment']) ? (string) $payload['comment'] : null;

        $result = $this->commandBus->dispatch(new CancelRefundCommand($id, $transactionId, $comment));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    /** @deprecated Prefer cancelRefund */
    #[Route('/api/assurances/lots/{id}/recover/cancel', name: 'api_assurances_lots_recover_cancel', methods: ['PATCH'])]
    public function cancelLotRecovery(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?: [];
        $comment = isset($payload['comment']) ? (string) $payload['comment'] : null;

        $result = $this->commandBus->dispatch(new CancelLotRecoveryCommand($id, $comment));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/lots/{id}/claims', name: 'api_assurances_lots_add_claim', methods: ['POST'])]
    public function addClaimToLot(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?: [];
        $factureId = (int) ($payload['factureId'] ?? $payload['claimId'] ?? 0);

        $result = $this->commandBus->dispatch(new AddClaimToLotCommand($id, $factureId));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/claims/{factureId}/move-lot', name: 'api_assurances_claims_move_lot', methods: ['POST'], requirements: ['factureId' => '\d+'])]
    public function moveClaimToLot(int $factureId, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?: [];
        $lotId = (int) ($payload['lotId'] ?? 0);

        $result = $this->commandBus->dispatch(new MoveClaimToLotCommand($factureId, $lotId));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/lots/{id}/claims/{factureId}', name: 'api_assurances_lots_remove_claim', methods: ['DELETE'])]
    public function removeClaimFromLot(int $id, int $factureId): JsonResponse
    {
        $result = $this->commandBus->dispatch(new RemoveClaimFromLotCommand($id, $factureId));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 400);
        }

        return $this->json($result);
    }

    #[Route('/api/assurances/claims/{id}', name: 'api_assurances_claim_detail', methods: ['GET'], requirements: ['id' => '\d+'], priority: -10)]
    public function getClaimDetail(int $id): JsonResponse
    {
        $result = $this->queryBus->ask(new GetClaimDetailQuery($id));

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status'] ?? 404);
        }

        return $this->json($result);
    }
}
