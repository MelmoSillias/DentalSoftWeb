<?php

namespace App\Scheduling\Service;

use App\Scheduling\Entity\Rdv;
use App\IdentityAccess\Entity\User;
use App\Communication\Service\NotificationLinkBuilder;
use App\Shared\Event\EntityActionEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class RdvNotificationService
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
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
        $patientName = trim($rdv->getPatient()?->getFullName() ?? '') ?: ($rdv->getPatient()?->getNom() ?? 'Patient');
        $dateLabel = $rdv->getDateRdv()?->format('d/m/Y H:i') ?? 'une date à confirmer';

        $message = '';
        $priority = 'info';
        $type = 'info';

        switch ($event) {
            case 'created':
                $suffix = !empty($context['from_report']) ? ' (suite à un report)' : '';
                $message = sprintf('Nouveau rendez-vous pour %s le %s%s.', $patientName, $dateLabel, $suffix);
                $type = 'success';
                break;
            case 'validated':
                $message = sprintf('Le rendez-vous de %s prévu le %s a été validé.', $patientName, $dateLabel);
                $type = 'success';
                break;
            case 'cancelled':
                $message = sprintf('Le rendez-vous de %s prévu le %s a été annulé.', $patientName, $dateLabel);
                $priority = 'warning';
                $type = 'warning';
                break;
            case 'reported':
                $newDate = $context['new_date'] ?? null;
                $newDateLabel = $newDate instanceof \DateTimeInterface ? $newDate->format('d/m/Y H:i') : 'une nouvelle date à préciser';
                $message = sprintf('Le rendez-vous de %s initialement prévu le %s est reporté au %s.', $patientName, $dateLabel, $newDateLabel);
                $priority = 'warning';
                $type = 'warning';
                break;
            default:
                return;
        }

        $this->eventDispatcher->dispatch(
            new EntityActionEvent(
                $rdv,
                $event,
                ['ROLE_MEDECIN'],
                $emitter,
                [
                    'message' => $message,
                    'priority' => $priority,
                    'type' => $type,
                    'link' => NotificationLinkBuilder::AGENDA_RDV,
                ],
            )
        );
    }
}
