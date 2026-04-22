<?php

namespace App\Service;

use App\Entity\Patient;
use App\Entity\SmsLog;
use App\Entity\SmsQueue;
use App\Repository\PatientRepository;
use App\Repository\SmsLogRepository;
use App\Repository\SmsQueueRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class SmsService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SmsQueueRepository $queueRepository,
        private readonly SmsLogRepository $logRepository,
        private readonly PatientRepository $patientRepository,
        private readonly SmsConfigService $configService,
        private readonly SmsTemplateService $templateService,
        private readonly OrangeSmsClient $orangeSmsClient,
        private readonly NotificationService $notificationService,
        private readonly NotificationRecipientResolver $recipientResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $variables
     * @return array{success: bool, queueId?: int, error?: string}
     */
    public function queueTemplateForPatient(Patient $patient, string $templateCode, array $variables, string $source = 'automation', ?DateTimeImmutable $sendAt = null): array
    {
        if ($patient->isSmsUnsubscribed() || $patient->isSmsBlacklisted()) {
            return ['success' => false, 'error' => 'Patient désinscrit ou blacklisté pour les SMS.'];
        }

        $message = $this->templateService->renderByCode($templateCode, $variables);
        if ($message === null) {
            return ['success' => false, 'error' => 'Template SMS indisponible ou désactivé.'];
        }

        $allowed = $this->isPatientTemplateAllowed($patient, $templateCode);
        if (!$allowed) {
            return ['success' => false, 'error' => 'Préférence patient SMS désactivée pour ce type.'];
        }

        return $this->queueManual(
            $patient->getTelephone() ?? '',
            $message,
            $patient,
            $this->mapTemplateCodeToType($templateCode),
            $source,
            $sendAt,
            ['templateCode' => $templateCode, 'variables' => $variables]
        );
    }

    /**
     * @param array<string, mixed>|null $metadata
     * @return array{success: bool, queueId?: int, error?: string}
     */
    public function queueManual(
        string $phone,
        string $message,
        ?Patient $patient = null,
        string $type = 'manual',
        string $source = 'manual',
        ?DateTimeImmutable $sendAt = null,
        ?array $metadata = null,
    ): array {
        $check = $this->configService->validateReadyConfig();
        if (!$check['valid']) {
            return ['success' => false, 'error' => $check['message'] ?? 'Module SMS indisponible'];
        }

        if (trim($phone) === '' || trim($message) === '') {
            return ['success' => false, 'error' => 'Téléphone et message sont requis'];
        }

        $queue = (new SmsQueue())
            ->setPhone($phone)
            ->setMessage(mb_substr($message, 0, 160))
            ->setType($type)
            ->setSource($source)
            ->setStatus(SmsQueue::STATUS_PENDING)
            ->setRetryCount(0)
            ->setSendAt($sendAt)
            ->setMetadata($metadata);

        if ($patient instanceof Patient) {
            $queue->setPatient($patient);
        }

        $this->entityManager->persist($queue);
        $this->entityManager->flush();

        return ['success' => true, 'queueId' => (int) $queue->getId()];
    }

    /**
     * @return array<string, mixed>
     */
    public function processQueue(int $limit = 20): array
    {
        $items = $this->queueRepository->findProcessable($limit);
        $sent = 0;
        $failed = 0;

        foreach ($items as $queueItem) {
            $queueItem->setStatus(SmsQueue::STATUS_SENDING);
            $this->entityManager->persist($queueItem);
            $this->entityManager->flush();

            $result = $this->orangeSmsClient->sendSms($queueItem->getPhone(), $queueItem->getMessage());

            if (($result['success'] ?? false) === true) {
                $queueItem
                    ->setStatus(SmsQueue::STATUS_SENT)
                    ->setSentAt(new DateTimeImmutable())
                    ->setLastError(null);

                $log = (new SmsLog())
                    ->setPatient($queueItem->getPatient())
                    ->setPhone($queueItem->getPhone())
                    ->setMessage($queueItem->getMessage())
                    ->setStatus('sent')
                    ->setType($queueItem->getType())
                    ->setSource($queueItem->getSource())
                    ->setProvider('orange')
                    ->setProviderMessageId(isset($result['providerMessageId']) ? (string) $result['providerMessageId'] : null);

                $this->entityManager->persist($log);
                ++$sent;
            } else {
                $retry = $queueItem->getRetryCount() + 1;
                $error = (string) ($result['error'] ?? 'Erreur d\'envoi SMS');

                $queueItem
                    ->setRetryCount($retry)
                    ->setStatus(SmsQueue::STATUS_FAILED)
                    ->setLastError($error)
                    ->setSendAt((new DateTimeImmutable())->modify('+5 minutes'));

                $log = (new SmsLog())
                    ->setPatient($queueItem->getPatient())
                    ->setPhone($queueItem->getPhone())
                    ->setMessage($queueItem->getMessage())
                    ->setStatus('failed')
                    ->setType($queueItem->getType())
                    ->setSource($queueItem->getSource())
                    ->setProvider('orange')
                    ->setErrorMessage($error);

                $this->entityManager->persist($log);

                if ($retry >= 3) {
                    $this->notifyAdminFailure($queueItem, $error);
                }

                ++$failed;
            }

            $this->entityManager->persist($queueItem);
            $this->entityManager->flush();
        }

        return [
            'processed' => count($items),
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function testSend(string $phone, string $message): array
    {
        $result = $this->orangeSmsClient->sendSms($phone, $message);

        $log = (new SmsLog())
            ->setPhone($phone)
            ->setMessage($message)
            ->setStatus(($result['success'] ?? false) ? 'sent' : 'failed')
            ->setType('manual')
            ->setSource('test')
            ->setProvider('orange')
            ->setProviderMessageId(isset($result['providerMessageId']) ? (string) $result['providerMessageId'] : null)
            ->setErrorMessage(isset($result['error']) ? (string) $result['error'] : null);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $todayStart = new DateTimeImmutable('today 00:00:00');
        $todayEnd = new DateTimeImmutable('today 23:59:59');
        $monthStart = new DateTimeImmutable('first day of this month 00:00:00');
        $monthEnd = new DateTimeImmutable('last day of this month 23:59:59');

        return [
            'balance' => [
                'sentToday' => $this->logRepository->countSentBetween($todayStart, $todayEnd),
                'sentMonth' => $this->logRepository->countSentBetween($monthStart, $monthEnd),
                'totalSent' => $this->logRepository->countTotalSent(),
            ],
            'dailyConsumption' => $this->logRepository->dailySentSeries(7),
            'monthlyConsumption' => $this->logRepository->monthlySentSeries(6),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listLogs(int $limit = 50, int $offset = 0): array
    {
        $logs = $this->logRepository->findRecent($limit, $offset);

        return array_map(static function (SmsLog $log): array {
            $patient = $log->getPatient();

            return [
                'id' => $log->getId(),
                'date' => $log->getCreatedAt()->format('Y-m-d H:i:s'),
                'patient' => $patient ? trim(($patient->getPrenom() ?? '') . ' ' . ($patient->getNom() ?? '')) : null,
                'phone' => $log->getPhone(),
                'message' => $log->getMessage(),
                'status' => $log->getStatus(),
                'type' => $log->getType(),
                'source' => $log->getSource(),
                'error' => $log->getErrorMessage(),
            ];
        }, $logs);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTemplates(): array
    {
        return $this->templateService->listTemplates();
    }

    /**
     * @param array<int, array<string, mixed>> $templates
     */
    public function saveTemplates(array $templates): void
    {
        $this->templateService->saveTemplates($templates);
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function previewTemplate(string $code, array $variables): ?string
    {
        return $this->templateService->renderByCode($code, $variables);
    }

    public function findPatient(?int $patientId): ?Patient
    {
        if (!$patientId) {
            return null;
        }

        return $this->patientRepository->find($patientId);
    }

    private function notifyAdminFailure(SmsQueue $queue, string $error): void
    {
        $recipients = $this->recipientResolver->admins();
        if ($recipients === []) {
            return;
        }

        $message = sprintf('Échec SMS (%s) vers %s après 3 tentatives: %s', $queue->getType(), $queue->getPhone(), $error);
        $this->notificationService->notifyMany($recipients, $message, 'warning', '/parametres/apparence', 'warning');
    }

    private function mapTemplateCodeToType(string $templateCode): string
    {
        return match ($templateCode) {
            'appointment_reminder' => 'appointment reminder',
            default => str_replace('_', ' ', $templateCode),
        };
    }

    private function isPatientTemplateAllowed(Patient $patient, string $templateCode): bool
    {
        return match ($templateCode) {
            'patient_created' => $patient->isSmsPatientCreated(),
            'receipt' => $patient->isSmsReceipt(),
            'invoice' => $patient->isSmsInvoice(),
            'ticket' => $patient->isSmsTicket(),
            'appointment_reminder' => $patient->isSmsAppointmentReminder(),
            default => true,
        };
    }
}
