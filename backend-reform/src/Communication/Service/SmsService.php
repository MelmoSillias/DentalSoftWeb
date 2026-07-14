<?php

namespace App\Communication\Service;

use App\Communication\Entity\SmsLog;
use App\Communication\Entity\SmsQueue;
use App\Communication\Repository\SmsLogRepository;
use App\Communication\Repository\SmsQueueRepository;
use App\Patient\Entity\Patient;
use App\Patient\Repository\PatientRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class SmsService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SmsQueueRepository $queueRepository,
        private readonly SmsLogRepository $logRepository,
        private readonly PatientRepository $patientRepository,
        private readonly \App\Communication\Service\SmsConfigService $configService,
        private readonly \App\Communication\Service\SmsTemplateService $templateService,
        private readonly \App\Communication\Service\OrangeSmsClient $orangeSmsClient,
        private readonly NotificationService $notificationService,
        private readonly \App\Communication\Service\NotificationRecipientResolver $recipientResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<string, mixed>|null $metadata
     * @return array{success: bool, queueId?: int, error?: string}
     */
    public function queueTemplateForPatient(Patient $patient, string $templateCode, array $variables, string $source = 'automation', ?DateTimeImmutable $sendAt = null, ?array $metadata = null): array
    {
        if ($patient->isSmsUnsubscribed() && !$this->configService->shouldBypassPatientPreference('unsubscribed')) {
            return ['success' => false, 'error' => 'Patient désinscrit ou blacklisté pour les SMS.'];
        }

        if ($patient->isSmsBlacklisted() && !$this->configService->shouldBypassPatientPreference('blacklisted')) {
            return ['success' => false, 'error' => 'Patient désinscrit ou blacklisté pour les SMS.'];
        }

        $message = $this->templateService->renderByCode($templateCode, $variables);
        if ($message === null) {
            return ['success' => false, 'error' => 'Template SMS indisponible ou désactivé.'];
        }

        $allowed = $this->isPatientTemplateAllowed($patient, $templateCode);
        if (!$allowed) {
            $preferenceKey = $this->mapTemplateCodeToPreferenceKey($templateCode);
            if ($preferenceKey !== null && $this->configService->shouldBypassPatientPreference($preferenceKey)) {
                $allowed = true;
            }
        }

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
            array_merge(['templateCode' => $templateCode, 'variables' => $variables], $metadata ?? [])
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
        $snapshotBefore = $this->queueRepository->getProcessingSnapshot();
        $items = $this->queueRepository->findProcessable($limit);
        $sent = 0;
        $failed = 0;

        foreach ($items as $queueItem) {
            $queueItem->setStatus(SmsQueue::STATUS_SENDING);
            $this->entityManager->persist($queueItem);
            $this->entityManager->flush();

            // Valider la configuration avant envoi pour produire une erreur descriptive
            $config = $this->configService->getConfig();
            $check = $this->configService->validateReadyConfig($config);
            if (!($check['valid'] ?? false)) {
                $hasClientId = $config->getClientId() ? 'oui' : 'non';
                $hasClientSecret = $this->configService->getClientSecret($config) ? 'oui' : 'non';
                $sender = $config->getSenderAddress() ?: 'absent';
                $detailed = sprintf("%s (clientId:%s, secret:%s, sender:%s)", $check['message'] ?? 'Configuration SMS invalide', $hasClientId, $hasClientSecret, $sender);
                $result = ['success' => false, 'error' => $detailed];
            } else {
                $result = $this->orangeSmsClient->sendSms($queueItem->getPhone(), $queueItem->getMessage());
            }

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
            'snapshot' => [
                'before' => [
                    'pendingDue' => (int) ($snapshotBefore['pendingDue'] ?? 0),
                    'pendingScheduled' => (int) ($snapshotBefore['pendingScheduled'] ?? 0),
                    'failedDue' => (int) ($snapshotBefore['failedDue'] ?? 0),
                    'failedScheduled' => (int) ($snapshotBefore['failedScheduled'] ?? 0),
                    'failedExhausted' => (int) ($snapshotBefore['failedExhausted'] ?? 0),
                    'sending' => (int) ($snapshotBefore['sending'] ?? 0),
                    'sent' => (int) ($snapshotBefore['sent'] ?? 0),
                    'cancelled' => (int) ($snapshotBefore['cancelled'] ?? 0),
                    'nextScheduledAt' => ($snapshotBefore['nextScheduledAt'] ?? null) instanceof DateTimeImmutable
                        ? $snapshotBefore['nextScheduledAt']->format('Y-m-d H:i:s')
                        : null,
                ],
            ],
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
    public function stats(?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): array
    {
        $todayStart = new DateTimeImmutable('today 00:00:00');
        $todayEnd = new DateTimeImmutable('today 23:59:59');
        $monthStart = new DateTimeImmutable('first day of this month 00:00:00');
        $monthEnd = new DateTimeImmutable('last day of this month 23:59:59');

        $periodStart = ($from ?? $monthStart)->setTime(0, 0, 0);
        $periodEnd = ($to ?? $monthEnd)->setTime(23, 59, 59);

        $sent = $this->logRepository->countByStatusBetween($periodStart, $periodEnd, 'sent');
        $failed = $this->logRepository->countByStatusBetween($periodStart, $periodEnd, 'failed');
        $total = $this->logRepository->countBetween($periodStart, $periodEnd);
        $successRate = $total > 0 ? round(($sent / $total) * 100, 1) : 0.0;

        return [
            'balance' => [
                'sentToday' => $this->logRepository->countSentBetween($todayStart, $todayEnd),
                'sentMonth' => $this->logRepository->countSentBetween($monthStart, $monthEnd),
                'totalSent' => $this->logRepository->countTotalSent(),
            ],
            'dailyConsumption' => $this->logRepository->dailySentSeries(7),
            'monthlyConsumption' => $this->logRepository->monthlySentSeries(6),
            'period' => [
                'from' => $periodStart->format('Y-m-d'),
                'to' => $periodEnd->format('Y-m-d'),
                'sent' => $sent,
                'failed' => $failed,
                'total' => $total,
                'successRate' => $successRate,
                'daily' => $this->logRepository->dailySentSeriesBetween($periodStart, $periodEnd),
                'byType' => $this->logRepository->sentByTypeBetween($periodStart, $periodEnd),
            ],
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
    public function listQueue(int $limit = 100, int $offset = 0, ?string $status = null): array
    {
        $items = $this->queueRepository->findRecentQueue($limit, $offset, $status);
        $now = new DateTimeImmutable();

        return array_map(static function (SmsQueue $item) use ($now): array {
            $patient = $item->getPatient();
            $metadata = $item->getMetadata() ?? [];
            $sendAt = $item->getSendAt();
            $isScheduled = $item->getStatus() === SmsQueue::STATUS_PENDING && $sendAt instanceof DateTimeImmutable && $sendAt > $now;

            return [
                'id' => $item->getId(),
                'createdAt' => $item->getCreatedAt()->format('Y-m-d H:i:s'),
                'sendAt' => $sendAt?->format('Y-m-d H:i:s'),
                'sentAt' => $item->getSentAt()?->format('Y-m-d H:i:s'),
                'patientId' => $patient?->getId(),
                'patient' => $patient ? trim(($patient->getPrenom() ?? '') . ' ' . ($patient->getNom() ?? '')) : null,
                'phone' => $item->getPhone(),
                'message' => $item->getMessage(),
                'status' => $item->getStatus(),
                'type' => $item->getType(),
                'source' => $item->getSource(),
                'retryCount' => $item->getRetryCount(),
                'lastError' => $item->getLastError(),
                'isScheduled' => $isScheduled,
                'metadata' => $metadata,
            ];
        }, $items);
    }

    /**
     * Retourne les détails d'un élément de file et les logs associés.
     *
     * @return array<string, mixed>
     */
    public function getQueueDetails(int $queueId, int $logsLimit = 50): array
    {
        $item = $this->queueRepository->find($queueId);
        if (!$item instanceof SmsQueue) {
            return ['success' => false, 'error' => 'Élément introuvable.', 'statusCode' => 404];
        }

        $logs = [];
        try {
            $since = $item->getCreatedAt();
            $found = $this->logRepository->findByPhoneSince($item->getPhone(), $since, $logsLimit);
            foreach ($found as $log) {
                $logs[] = [
                    'id' => $log->getId(),
                    'date' => $log->getCreatedAt()->format('Y-m-d H:i:s'),
                    'status' => $log->getStatus(),
                    'provider' => $log->getProvider(),
                    'providerMessageId' => $log->getProviderMessageId(),
                    'error' => $log->getErrorMessage(),
                    'message' => $log->getMessage(),
                ];
            }
        } catch (\Throwable $e) {
            // ignore and return minimal details
        }

        return [
            'success' => true,
            'queueItem' => [
                'id' => $item->getId(),
                'createdAt' => $item->getCreatedAt()->format('Y-m-d H:i:s'),
                'sendAt' => $item->getSendAt()?->format('Y-m-d H:i:s'),
                'sentAt' => $item->getSentAt()?->format('Y-m-d H:i:s'),
                'patient' => $item->getPatient() ? trim(($item->getPatient()->getPrenom() ?? '') . ' ' . ($item->getPatient()->getNom() ?? '')) : null,
                'phone' => $item->getPhone(),
                'message' => $item->getMessage(),
                'status' => $item->getStatus(),
                'retryCount' => $item->getRetryCount(),
                'lastError' => $item->getLastError(),
                'metadata' => $item->getMetadata(),
            ],
            'logs' => $logs,
        ];
    }

    /**
     * @return array{success: bool, queueId?: int, status?: string, sendAt?: string|null, error?: string, statusCode?: int}
     */
    public function updateQueueItem(int $queueId, string $action, ?DateTimeImmutable $sendAt = null): array
    {
        $item = $this->queueRepository->find($queueId);
        if (!$item instanceof SmsQueue) {
            return ['success' => false, 'error' => 'Élément de file introuvable.', 'statusCode' => 404];
        }

        switch ($action) {
            case 'cancel':
                if ($item->getStatus() !== SmsQueue::STATUS_PENDING) {
                    return ['success' => false, 'error' => 'Seuls les SMS en attente peuvent être annulés.', 'statusCode' => 400];
                }
                $item
                    ->setStatus(SmsQueue::STATUS_CANCELLED)
                    ->setLastError(null);
                break;

            case 'reschedule':
                if ($item->getStatus() !== SmsQueue::STATUS_PENDING) {
                    return ['success' => false, 'error' => 'Seuls les SMS en attente peuvent être reprogrammés.', 'statusCode' => 400];
                }
                if (!$sendAt instanceof DateTimeImmutable) {
                    return ['success' => false, 'error' => 'Date de reprogrammation requise.', 'statusCode' => 400];
                }
                $item
                    ->setStatus(SmsQueue::STATUS_PENDING)
                    ->setSendAt($sendAt)
                    ->setLastError(null);
                break;

            case 'retry':
                if ($item->getStatus() !== SmsQueue::STATUS_FAILED) {
                    return ['success' => false, 'error' => 'Seuls les SMS échoués peuvent être renvoyés.', 'statusCode' => 400];
                }
                $item
                    ->setStatus(SmsQueue::STATUS_PENDING)
                    ->setRetryCount(0)
                    ->setLastError(null)
                    ->setSentAt(null)
                    ->setSendAt($sendAt ?? new DateTimeImmutable());
                break;

            default:
                return ['success' => false, 'error' => 'Action de file SMS invalide.', 'statusCode' => 400];
        }

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return [
            'success' => true,
            'queueId' => (int) $item->getId(),
            'status' => $item->getStatus(),
            'sendAt' => $item->getSendAt()?->format('Y-m-d H:i:s'),
        ];
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

    private function mapTemplateCodeToPreferenceKey(string $templateCode): ?string
    {
        return match ($templateCode) {
            'patient_created' => 'patientCreated',
            'receipt' => 'receipt',
            'invoice' => 'invoice',
            'ticket' => 'ticket',
            'appointment_reminder' => 'appointmentReminder',
            default => null,
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
