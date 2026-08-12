<?php

namespace App\Communication\Controller\Api;

use App\Billing\Entity\Devis;
use App\Billing\Entity\Paiement;
use App\Billing\Repository\DevisRepository;
use App\Billing\Repository\PaiementRepository;
use App\Communication\Message\ProcessSmsQueueMessage;
use App\Patient\Entity\Patient;
use App\Scheduling\Entity\Rdv;
use App\Scheduling\Repository\RdvRepository;
use App\Communication\Service\SmsClientResolver;
use App\Communication\Service\SmsConfigService;
use App\Communication\Service\SmsService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/sms', name: 'api_sms_')]
final class SmsController extends AbstractController
{
    /**
     * @return list<DateTimeImmutable|null>
     */
    private function buildManualSendDates(?DateTimeImmutable $sendAt, string $recurrence): array
    {
        if (!$sendAt instanceof DateTimeImmutable) {
            return [null];
        }

        return match ($recurrence) {
            'daily_3' => [
                $sendAt,
                $sendAt->modify('+1 day'),
                $sendAt->modify('+2 days'),
            ],
            'weekly_4' => [
                $sendAt,
                $sendAt->modify('+1 week'),
                $sendAt->modify('+2 weeks'),
                $sendAt->modify('+3 weeks'),
            ],
            default => [$sendAt],
        };
    }

    public function __construct(
        private readonly SmsConfigService $smsConfigService,
        private readonly SmsService $smsService,
        private readonly SmsClientResolver $smsClientResolver,
        private readonly MessageBusInterface $messageBus,
        private readonly RdvRepository $rdvRepository,
        private readonly DevisRepository $devisRepository,
        private readonly PaiementRepository $paiementRepository,
    ) {
    }

    #[Route('/settings', name: 'settings_get', methods: ['GET'])]
    public function getSettings(): JsonResponse
    {
        return $this->json($this->smsConfigService->getPublicConfig());
    }

    #[Route('/settings', name: 'settings_save', methods: ['PUT'])]
    public function saveSettings(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $saved = $this->smsConfigService->saveConfig($payload);

        $config = $this->smsConfigService->getConfig();
        if ($config->getProvider() === SmsClientResolver::PROVIDER_AFRIKSMS && $config->isEnabled()) {
            $notifyUrl = $this->smsConfigService->buildAfrikSmsWebhookUrl($config);
            if ($notifyUrl !== null) {
                $saved['callbackRegistration'] = $this->smsClientResolver->getAfrikSmsClient()->configureCallbackUrl(
                    $notifyUrl,
                    $config->getCallbackNotifyType(),
                    $config
                );
            } else {
                $saved['callbackRegistration'] = [
                    'success' => false,
                    'message' => 'URL webhook publique non configurée. Les accusés de réception AfrikSms ne seront pas enregistrés.',
                ];
            }
        }

        return $this->json($saved);
    }

    #[Route('/test-connection', name: 'test_connection', methods: ['POST'])]
    public function testConnection(): JsonResponse
    {
        $result = $this->smsClientResolver->getClient()->testConnection();

        return $this->json($result, ($result['success'] ?? false) ? 200 : 400);
    }

    #[Route('/test-send', name: 'test_send', methods: ['POST'])]
    public function testSend(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $phone = trim((string) ($payload['phone'] ?? ''));
        $message = trim((string) ($payload['message'] ?? 'Message de test DentalSoft.'));

        if ($phone === '') {
            return $this->json(['success' => false, 'error' => 'Numéro requis'], 400);
        }

        $result = $this->smsService->testSend($phone, $message);

        return $this->json($result, ($result['success'] ?? false) ? 200 : 400);
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        $fromRaw = trim((string) $request->query->get('from', ''));
        $toRaw = trim((string) $request->query->get('to', ''));

        $from = null;
        $to = null;

        if ($fromRaw !== '') {
            $from = DateTimeImmutable::createFromFormat('!Y-m-d', $fromRaw);
            if (!$from instanceof DateTimeImmutable) {
                return $this->json(['error' => 'Paramètre from invalide (attendu Y-m-d).'], 400);
            }
        }

        if ($toRaw !== '') {
            $to = DateTimeImmutable::createFromFormat('!Y-m-d', $toRaw);
            if (!$to instanceof DateTimeImmutable) {
                return $this->json(['error' => 'Paramètre to invalide (attendu Y-m-d).'], 400);
            }
        }

        if ($from instanceof DateTimeImmutable && $to instanceof DateTimeImmutable && $from > $to) {
            return $this->json(['error' => 'La date de début doit précéder la date de fin.'], 400);
        }

        if ($from instanceof DateTimeImmutable && $to instanceof DateTimeImmutable) {
            $days = (int) $from->diff($to)->days;
            if ($days > 92) {
                return $this->json(['error' => 'La période ne peut pas dépasser 92 jours.'], 400);
            }
        }

        return $this->json($this->smsService->stats($from, $to));
    }

    #[Route('/provider-overview', name: 'provider_overview', methods: ['GET'])]
    public function providerOverview(): JsonResponse
    {
        $result = $this->smsClientResolver->getClient()->fetchProviderOverview();

        return $this->json($result, ($result['success'] ?? false) ? 200 : 400);
    }

    #[Route('/webhooks/afriksms', name: 'webhook_afriksms', methods: ['GET', 'POST'])]
    public function afrikSmsWebhook(Request $request): JsonResponse
    {
        $payload = $request->getMethod() === 'POST'
            ? (json_decode($request->getContent(), true) ?? $request->request->all())
            : $request->query->all();

        $resourceId = trim((string) ($payload['resourceId'] ?? ''));
        $code = trim((string) ($payload['code'] ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));

        if ($resourceId === '' || $code === '') {
            return $this->json(['success' => false, 'message' => 'Accusé de réception incomplet.'], 400);
        }

        $result = $this->smsService->handleDeliveryReport(
            SmsClientResolver::PROVIDER_AFRIKSMS,
            $resourceId,
            $code,
            $message
        );

        return $this->json($result, ($result['success'] ?? false) ? 200 : 404);
    }

    #[Route('/logs', name: 'logs', methods: ['GET'])]
    public function logs(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 50);
        $offset = (int) $request->query->get('offset', 0);

        return $this->json($this->smsService->listLogs($limit, $offset));
    }

    #[Route('/queue', name: 'queue_list', methods: ['GET'])]
    public function queue(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 100);
        $offset = (int) $request->query->get('offset', 0);
        $status = $request->query->get('status');

        return $this->json($this->smsService->listQueue($limit, $offset, is_string($status) ? $status : null));
    }

    #[Route('/queue/{id}/details', name: 'queue_details', methods: ['GET'])]
    public function queueDetails(int $id): JsonResponse
    {
        $result = $this->smsService->getQueueDetails($id, 50);
        $statusCode = (int) ($result['statusCode'] ?? (($result['success'] ?? false) ? 200 : 400));
        unset($result['statusCode']);

        return $this->json($result, $statusCode);
    }

    #[Route('/queue/{id}', name: 'queue_update', methods: ['PATCH'])]
    public function updateQueueItem(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $action = (string) ($payload['action'] ?? '');
        $sendAtRaw = $payload['sendAt'] ?? null;
        $sendAt = null;

        if (is_string($sendAtRaw) && trim($sendAtRaw) !== '') {
            try {
                $sendAt = new DateTimeImmutable($sendAtRaw);
            } catch (\Throwable) {
                return $this->json(['success' => false, 'error' => 'Date de programmation invalide'], 400);
            }
        }

        $result = $this->smsService->updateQueueItem($id, $action, $sendAt);
        $statusCode = (int) ($result['statusCode'] ?? (($result['success'] ?? false) ? 200 : 400));
        unset($result['statusCode']);

        return $this->json($result, $statusCode);
    }

    #[Route('/templates', name: 'templates_list', methods: ['GET'])]
    public function listTemplates(): JsonResponse
    {
        return $this->json($this->smsService->listTemplates());
    }

    #[Route('/templates', name: 'templates_save', methods: ['PUT'])]
    public function saveTemplates(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $templates = isset($payload['templates']) && is_array($payload['templates']) ? $payload['templates'] : [];

        $this->smsService->saveTemplates($templates);

        return $this->json(['success' => true]);
    }

    #[Route('/templates/preview', name: 'templates_preview', methods: ['POST'])]
    public function previewTemplate(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $code = (string) ($payload['code'] ?? '');
        $variables = isset($payload['variables']) && is_array($payload['variables']) ? $payload['variables'] : [];

        if ($code === '') {
            return $this->json(['success' => false, 'error' => 'Template code requis'], 400);
        }

        $preview = $this->smsService->previewTemplate($code, $variables);
        if ($preview === null) {
            return $this->json(['success' => false, 'error' => 'Template introuvable ou désactivé'], 404);
        }

        return $this->json([
            'success' => true,
            'message' => $preview,
            'characters' => mb_strlen($preview),
            'estimatedSmsCount' => (int) ceil(max(1, mb_strlen($preview)) / 160),
        ]);
    }

    #[Route('/send/manual', name: 'send_manual', methods: ['POST'])]
    public function sendManual(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $phone = trim((string) ($payload['phone'] ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));
        $patientId = isset($payload['patientId']) ? (int) $payload['patientId'] : null;
        $patient = $this->smsService->findPatient($patientId);
        $recurrence = (string) ($payload['recurrence'] ?? 'none');
        $sendAtRaw = $payload['sendAt'] ?? null;
        $sendAt = null;

        if (is_string($sendAtRaw) && trim($sendAtRaw) !== '') {
            try {
                $sendAt = new DateTimeImmutable($sendAtRaw);
            } catch (\Throwable) {
                return $this->json(['success' => false, 'error' => 'Date de programmation invalide'], 400);
            }
        }

        $dates = $this->buildManualSendDates($sendAt, $recurrence);
        $queuedIds = [];
        $skippedCount = 0;
        $now = new DateTimeImmutable();

        foreach ($dates as $index => $itemSendAt) {
            if ($itemSendAt instanceof DateTimeImmutable && $itemSendAt <= $now) {
                ++$skippedCount;
                continue;
            }

            $result = $this->smsService->queueManual(
                $phone,
                $message,
                $patient,
                'manual',
                'manual',
                $itemSendAt,
                [
                    'recurrence' => $recurrence,
                    'occurrenceIndex' => $index + 1,
                    'occurrenceCount' => count($dates),
                ]
            );

            if (($result['success'] ?? false) !== true) {
                return $this->json($result, 400);
            }

            $queuedIds[] = (int) ($result['queueId'] ?? 0);
        }

        if ($queuedIds === []) {
            return $this->json(['success' => false, 'error' => 'Aucune occurrence future à programmer.'], 400);
        }

        return $this->json([
            'success' => true,
            'queueId' => $queuedIds[0],
            'queueIds' => $queuedIds,
            'queuedCount' => count($queuedIds),
            'skippedCount' => $skippedCount,
        ], 201);
    }

    #[Route('/queue/process', name: 'queue_process', methods: ['POST'])]
    public function processQueue(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $limit = (int) ($payload['limit'] ?? 20);
        $async = (bool) ($payload['async'] ?? false);

        if ($async) {
            $this->messageBus->dispatch(new ProcessSmsQueueMessage($limit));

            return $this->json(['success' => true, 'queued' => true]);
        }

        $result = $this->smsService->processQueue($limit);

        return $this->json(['success' => true] + $result);
    }

    #[Route('/appointments/{id}/send-reminder', name: 'appointment_send_reminder', methods: ['POST'])]
    public function sendAppointmentReminder(int $id, Request $request): JsonResponse
    {
        $rdv = $this->rdvRepository->find($id);
        if (!$rdv instanceof Rdv) {
            return $this->json(['success' => false, 'error' => 'Rendez-vous introuvable'], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $patient = $rdv->getPatient();
        if (!$patient instanceof Patient) {
            return $this->json(['success' => false, 'error' => 'Patient introuvable'], 404);
        }

        $date = $rdv->getDateRdv()?->format('d/m/Y') ?? '';
        $time = $rdv->getDateRdv()?->format('H:i') ?? '';

        $variables = [
            'patient_name' => trim(($patient->getPrenom() ?? '') . ' ' . ($patient->getNom() ?? '')),
            'date' => $date,
            'time' => $time,
        ];

        if (!empty($payload['message']) && is_string($payload['message'])) {
            $result = $this->smsService->queueManual(
                (string) $patient->getTelephone(),
                $payload['message'],
                $patient,
                'rappel de rdv',
                'appointment',
                null,
                ['rdvId' => $rdv->getId()]
            );
        } else {
            $result = $this->smsService->queueTemplateForPatient($patient, 'appointment_reminder', $variables, 'appointment', null, ['rdvId' => $rdv->getId()]);
        }

        return $this->json($result, ($result['success'] ?? false) ? 201 : 400);
    }

    #[Route('/appointments/{id}/schedule-reminder', name: 'appointment_schedule_reminder', methods: ['POST'])]
    public function scheduleReminder(int $id, Request $request): JsonResponse
    {
        $rdv = $this->rdvRepository->find($id);
        if (!$rdv instanceof Rdv) {
            return $this->json(['success' => false, 'error' => 'Rendez-vous introuvable'], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $hoursBefore = max(1, (int) ($payload['hoursBefore'] ?? 24));

        $rdvDate = $rdv->getDateRdv();
        if (!$rdvDate) {
            return $this->json(['success' => false, 'error' => 'Date de rendez-vous invalide'], 400);
        }

        $sendAt = DateTimeImmutable::createFromInterface($rdvDate)->modify(sprintf('-%d hours', $hoursBefore));
        $patient = $rdv->getPatient();
        if (!$patient instanceof Patient) {
            return $this->json(['success' => false, 'error' => 'Patient introuvable'], 404);
        }

        $variables = [
            'patient_name' => trim(($patient->getPrenom() ?? '') . ' ' . ($patient->getNom() ?? '')),
            'date' => $rdvDate->format('d/m/Y'),
            'time' => $rdvDate->format('H:i'),
        ];

        $result = $this->smsService->queueTemplateForPatient($patient, 'appointment_reminder', $variables, 'appointment-auto', $sendAt, ['rdvId' => $rdv->getId()]);

        return $this->json($result, ($result['success'] ?? false) ? 201 : 400);
    }

    #[Route('/invoices/{id}/send', name: 'invoice_send', methods: ['POST'])]
    public function sendInvoice(int $id, Request $request): JsonResponse
    {
        $devis = $this->devisRepository->find($id);
        if (!$devis instanceof Devis) {
            return $this->json(['success' => false, 'error' => 'Facture introuvable'], 404);
        }

        $patient = $this->resolvePatientFromDevis($devis);
        if (!$patient instanceof Patient) {
            return $this->json(['success' => false, 'error' => 'Patient introuvable pour cette facture'], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $variables = [
            'patient_name' => trim(($patient->getPrenom() ?? '') . ' ' . ($patient->getNom() ?? '')),
            'amount' => (string) ((int) round((float) ($devis->getMontant() ?? 0))),
            'invoice_number' => (string) $devis->getId(),
        ];

        $result = $this->smsService->queueTemplateForPatient($patient, 'invoice', $variables, 'invoice');

        return $this->json($result, ($result['success'] ?? false) ? 201 : 400);
    }

    #[Route('/receipts/{id}/send', name: 'receipt_send', methods: ['POST'])]
    public function sendReceipt(int $id, Request $request): JsonResponse
    {
        $paiement = $this->paiementRepository->find($id);
        if (!$paiement instanceof Paiement) {
            return $this->json(['success' => false, 'error' => 'ReÃ§u introuvable'], 404);
        }

        $patient = $this->resolvePatientFromPaiement($paiement);
        if (!$patient instanceof Patient) {
            return $this->json(['success' => false, 'error' => 'Patient introuvable pour ce reÃ§u'], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $variables = [
            'patient_name' => trim(($patient->getPrenom() ?? '') . ' ' . ($patient->getNom() ?? '')),
            'amount' => (string) ((int) round((float) $paiement->getMontant())),
            'date' => $paiement->getDate()?->format('d/m/Y') ?? '',
        ];

        $result = $this->smsService->queueTemplateForPatient($patient, 'receipt', $variables, 'receipt');

        return $this->json($result, ($result['success'] ?? false) ? 201 : 400);
    }

    private function resolvePatientFromDevis(Devis $devis): ?Patient
    {
        $fromFicheMedicale = $devis->getFicheMedicale()?->getPatient();
        if ($fromFicheMedicale instanceof Patient) {
            return $fromFicheMedicale;
        }

        $fiche = $devis->getFicheMedicale();
        if ($fiche && method_exists($fiche, 'getPatient')) {
            $patient = $fiche->getPatient();
            if ($patient instanceof Patient) {
                return $patient;
            }
        }

        return null;
    }

    private function resolvePatientFromPaiement(Paiement $paiement): ?Patient
    {
        $devis = $paiement->getFacture();
        if ($devis instanceof Devis) {
            $patient = $this->resolvePatientFromDevis($devis);
            if ($patient instanceof Patient) {
                return $patient;
            }
        }

        return $paiement->getConsultation()?->getPatient()
            ?? $paiement->getFactureAssurance()?->getPatient();
    }
}

