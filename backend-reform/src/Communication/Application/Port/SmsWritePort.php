<?php

namespace App\Communication\Application\Port;

use DateTimeImmutable;

interface SmsWritePort
{
    /**
     * @return array<string, mixed>
     */
    public function testSend(string $phone, string $message): array;

    /**
     * @return array<string, mixed>
     */
    public function updateQueueItem(int $queueId, string $action, ?DateTimeImmutable $sendAt = null): array;

    /**
     * @param list<array<string, mixed>> $templates
     */
    public function saveTemplates(array $templates): void;

    /**
     * @param array<string, mixed>|null $metadata
     *
     * @return array<string, mixed>
     */
    public function queueManual(
        string $phone,
        string $message,
        ?int $patientId = null,
        string $type = 'manual',
        string $source = 'manual',
        ?DateTimeImmutable $sendAt = null,
        ?array $metadata = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function processQueue(int $limit = 20): array;

    /**
     * @return array<string, mixed>
     */
    public function handleDeliveryReport(string $provider, string $resourceId, string $code, string $message): array;

    /**
     * @param array<string, mixed> $variables
     * @param array<string, mixed>|null $metadata
     *
     * @return array<string, mixed>
     */
    public function queueTemplateForPatient(
        int $patientId,
        string $templateCode,
        array $variables,
        string $source = 'automation',
        ?DateTimeImmutable $sendAt = null,
        ?array $metadata = null,
    ): array;
}
