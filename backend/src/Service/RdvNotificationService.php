<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Rdv;
use App\Entity\User;

final class RdvNotificationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly NotificationRecipientResolver $recipientResolver,
    ) {
    }

    public function notifyCreation(Rdv $rdv, ?User $emitter = null, bool $fromReport = false): void
    {
        $this->dispatch($rdv, 'created', $emitter, ['from_report' => $fromReport]);
    }

    public function notifyValidation(Rdv $rdv, ?User $emitter = null): void
    {
        $this->dispatch($rdv, 'validated', $emitter);
    }

    public function notifyCancellation(Rdv $rdv, ?User $emitter = null): void
    {
        $this->dispatch($rdv, 'cancelled', $emitter);
    }

    public function notifyReport(Rdv $rdv, ?\DateTimeInterface $newDate, ?User $emitter = null): void
    {
        $this->dispatch($rdv, 'reported', $emitter, ['new_date' => $newDate]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function dispatch(Rdv $rdv, string $event, ?User $emitter, array $context = []): void
    {
        $recipient = $this->recipientResolver->userForEmploye($rdv->getMedecin(), $emitter);
        if (!$recipient instanceof User) {
            return;
        }

        $patientName = trim($rdv->getPatient()?->getFullName() ?? '') ?: ($rdv->getPatient()?->getNom() ?? 'Patient');
        $dateLabel = $rdv->getDateRdv()?->format('d/m/Y H:i') ?? 'une date à confirmer';

        $message = '';
        $priority = Notification::PRIORITY_INFO;
        $type = Notification::TYPE_INFO;

        switch ($event) {
            case 'created':
                $suffix = !empty($context['from_report']) ? ' (suite à un report)' : '';
                $message = sprintf('Nouveau rendez-vous pour %s le %s%s.', $patientName, $dateLabel, $suffix);
                $type = Notification::TYPE_SUCCESS;
                break;
            case 'validated':
                $message = sprintf('Le rendez-vous de %s prévu le %s a été validé.', $patientName, $dateLabel);
                $type = Notification::TYPE_SUCCESS;
                break;
            case 'cancelled':
                $message = sprintf('Le rendez-vous de %s prévu le %s a été annulé.', $patientName, $dateLabel);
                $priority = Notification::PRIORITY_WARNING;
                $type = Notification::TYPE_WARNING;
                break;
            case 'reported':
                $newDate = $context['new_date'] ?? null;
                $newDateLabel = $newDate instanceof \DateTimeInterface ? $newDate->format('d/m/Y H:i') : 'une nouvelle date à préciser';
                $message = sprintf('Le rendez-vous de %s initialement prévu le %s est reporté au %s.', $patientName, $dateLabel, $newDateLabel);
                $priority = Notification::PRIORITY_WARNING;
                $type = Notification::TYPE_WARNING;
                break;
            default:
                return;
        }

        $this->notificationService->notify(
            $recipient,
            $message,
            $priority,
            '/medecin/agenda',
            $type,
            $emitter,
        );
    }
}
