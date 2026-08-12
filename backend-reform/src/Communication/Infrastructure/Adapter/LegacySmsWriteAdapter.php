<?php

namespace App\Communication\Infrastructure\Adapter;

use App\Communication\Application\Port\SmsWritePort;
use App\Communication\Service\SmsService;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Patient;
use DateTimeImmutable;

final class LegacySmsWriteAdapter implements SmsWritePort
{
    public function __construct(private readonly SmsService $smsService)
    {
    }

    public function testSend(string $phone, string $message): array
    {
        return $this->smsService->testSend($phone, $message);
    }

    public function updateQueueItem(int $queueId, string $action, ?DateTimeImmutable $sendAt = null): array
    {
        return $this->smsService->updateQueueItem($queueId, $action, $sendAt);
    }

    public function saveTemplates(array $templates): void
    {
        $this->smsService->saveTemplates($templates);
    }

    public function queueManual(
        string $phone,
        string $message,
        ?int $patientId = null,
        string $type = 'manual',
        string $source = 'manual',
        ?DateTimeImmutable $sendAt = null,
        ?array $metadata = null,
    ): array {
        $patient = $this->smsService->findPatient($patientId);

        return $this->smsService->queueManual(
            $phone,
            $message,
            $patient,
            $type,
            $source,
            $sendAt,
            $metadata,
        );
    }

    public function processQueue(int $limit = 20): array
    {
        return $this->smsService->processQueue($limit);
    }

    public function handleDeliveryReport(string $provider, string $resourceId, string $code, string $message): array
    {
        return $this->smsService->handleDeliveryReport($provider, $resourceId, $code, $message);
    }

    public function queueTemplateForPatient(
        int $patientId,
        string $templateCode,
        array $variables,
        string $source = 'automation',
        ?DateTimeImmutable $sendAt = null,
        ?array $metadata = null,
    ): array {
        $patient = $this->smsService->findPatient($patientId);
        if (!$patient instanceof Patient) {
            return ['success' => false, 'error' => 'Patient introuvable'];
        }

        return $this->smsService->queueTemplateForPatient(
            $patient,
            $templateCode,
            $variables,
            $source,
            $sendAt,
            $metadata,
        );
    }
}
