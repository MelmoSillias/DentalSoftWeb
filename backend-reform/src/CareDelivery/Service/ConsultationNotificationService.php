<?php

namespace App\CareDelivery\Service;

use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\Shared\Event\EntityActionEvent;
use App\Focus\Service\FocusRealtimePublisher;
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

        $link = $consultation->getId()
            ? sprintf('/medecin/consultation/%d/details', $consultation->getId())
            : '/medecin/consultation/en-attente';

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
