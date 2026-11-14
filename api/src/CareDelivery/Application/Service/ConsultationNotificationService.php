<?php

namespace App\CareDelivery\Application\Service;

use App\CareDelivery\Domain\Entity\Consultation;
use App\IdentityAccess\Domain\Entity\User;
use App\Communication\Application\Service\NotificationLinkBuilder;
use App\SharedKernel\Domain\Event\EntityActionEvent;
use App\Focus\Application\Service\FocusRealtimePublisher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ConsultationNotificationService
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FocusRealtimePublisher $focusRealtimePublisher,
    )
    {
    }

    public function notifyCreation(Consultation $consultation, ?User $emitter = null): void
    {
        $this->dispatch($consultation, 'creation', $emitter);
    }

    public function notifyCancellation(Consultation $consultation, ?User $emitter = null): void
    {
        $this->dispatch($consultation, 'cancellation', $emitter);
    }

    private function dispatch(Consultation $consultation, string $event, ?User $emitter): void
    {
        $this->focusRealtimePublisher->publishConsultationRefresh(
            $consultation,
            $event === 'creation' ? 'created' : 'cancelled',
        );

        $patient = $consultation->getPatient();
        $patientName = trim(sprintf('%s %s', $patient?->getNom() ?? '', $patient?->getPrenom() ?? '')) ?: 'un patient';
        $dateLabel = $consultation->getCreatedAt()?->format('d/m/Y H:i');

        if ($event === 'creation') {
            $message = sprintf(
                'Nouvelle consultation planifiée pour %s%s.',
                $patientName,
                $dateLabel ? ' le ' . $dateLabel : ''
            );
            $priority = 'info';
            $type = 'success';
        } else {
            $message = sprintf('Consultation de %s annulée.', $patientName);
            $priority = 'warning';
            $type = 'warning';
        }

        $link = NotificationLinkBuilder::consultation($consultation->getId(), true);

        $this->eventDispatcher->dispatch(
            new EntityActionEvent(
                $consultation,
                $event === 'creation' ? 'created' : 'cancelled',
                ['ROLE_MEDECIN'],
                $emitter,
                [
                    'message' => $message,
                    'priority' => $priority,
                    'type' => $type,
                    'link' => $link,
                ],
            )
        );
    }
}
