<?php

namespace App\Communication\Controller\Api;

use App\Billing\Entity\Devis;
use App\Billing\Entity\PaiementDevis;
use App\Billing\Repository\DevisRepository;
use App\Billing\Repository\PaiementDevisRepository;
use App\Communication\Message\ProcessSmsQueueMessage;
use App\Patient\Entity\Patient;
use App\Scheduling\Entity\Rdv;
use App\Scheduling\Repository\RdvRepository;
use App\Communication\Service\OrangeSmsClient;
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
    public function __construct(
        private readonly SmsConfigService $smsConfigService,
        private readonly SmsService $smsService,
        private readonly OrangeSmsClient $orangeSmsClient,
        private readonly MessageBusInterface $messageBus,
        private readonly RdvRepository $rdvRepository,
        private readonly DevisRepository $devisRepository,
        private readonly PaiementDevisRepository $paiementRepository,
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

        return $this->json($saved);
    }

    #[Route('/test-connection', name: 'test_connection', methods: ['POST'])]
    public function testConnection(): JsonResponse
    {
        $result = $this->orangeSmsClient->testConnection();

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
    public function stats(): JsonResponse
    {
        return $this->json($this->smsService->stats());
    }

    #[Route('/provider-overview', name: 'provider_overview', methods: ['GET'])]
    public function providerOverview(): JsonResponse
    {
        $result = $this->orangeSmsClient->fetchContractOverview();

        return $this->json($result, ($result['success'] ?? false) ? 200 : 400);
    }

    #[Route('/logs', name: 'logs', methods: ['GET'])]
    public function logs(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 50);
        $offset = (int) $request->query->get('offset', 0);

        return $this->json($this->smsService->listLogs($limit, $offset));
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

        $result = $this->smsService->queueManual($phone, $message, $patient, 'manual', 'manual');

        return $this->json($result, ($result['success'] ?? false) ? 201 : 400);
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
            'cabinet_name' => (string) ($payload['cabinet_name'] ?? 'ORODENT'),
        ];

        if (!empty($payload['message']) && is_string($payload['message'])) {
            $result = $this->smsService->queueManual(
                (string) $patient->getTelephone(),
                $payload['message'],
                $patient,
                'appointment reminder',
                'appointment'
            );
        } else {
            $result = $this->smsService->queueTemplateForPatient($patient, 'appointment_reminder', $variables, 'appointment');
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
            'cabinet_name' => (string) ($payload['cabinet_name'] ?? 'ORODENT'),
        ];

        $result = $this->smsService->queueTemplateForPatient($patient, 'appointment_reminder', $variables, 'appointment-auto', $sendAt);

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
            'cabinet_name' => (string) ($payload['cabinet_name'] ?? 'ORODENT'),
        ];

        $result = $this->smsService->queueTemplateForPatient($patient, 'invoice', $variables, 'invoice');

        return $this->json($result, ($result['success'] ?? false) ? 201 : 400);
    }

    #[Route('/receipts/{id}/send', name: 'receipt_send', methods: ['POST'])]
    public function sendReceipt(int $id, Request $request): JsonResponse
    {
        $paiement = $this->paiementRepository->find($id);
        if (!$paiement instanceof PaiementDevis) {
            return $this->json(['success' => false, 'error' => 'Reçu introuvable'], 404);
        }

        $patient = $this->resolvePatientFromPaiement($paiement);
        if (!$patient instanceof Patient) {
            return $this->json(['success' => false, 'error' => 'Patient introuvable pour ce reçu'], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $variables = [
            'patient_name' => trim(($patient->getPrenom() ?? '') . ' ' . ($patient->getNom() ?? '')),
            'amount' => (string) ((int) round((float) $paiement->getMontant())),
            'date' => $paiement->getDate()?->format('d/m/Y') ?? '',
            'cabinet_name' => (string) ($payload['cabinet_name'] ?? 'ORODENT'),
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

        $fiche = $devis->getFiche();
        if ($fiche && method_exists($fiche, 'getPatient')) {
            $patient = $fiche->getPatient();
            if ($patient instanceof Patient) {
                return $patient;
            }
        }

        return null;
    }

    private function resolvePatientFromPaiement(PaiementDevis $paiement): ?Patient
    {
        $devis = $paiement->getDevis();
        if ($devis instanceof Devis) {
            $patient = $this->resolvePatientFromDevis($devis);
            if ($patient instanceof Patient) {
                return $patient;
            }
        }

        return $paiement->getConsultation()?->getPatient();
    }
}
