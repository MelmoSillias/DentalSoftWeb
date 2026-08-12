<?php

namespace App\CareDelivery\Infrastructure\Adapter;

use App\CareDelivery\Application\Port\ConsultationNotificationPort;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation;
use App\CareDelivery\Service\ConsultationNotificationService;
use App\Communication\Service\NotificationRecipientResolver;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\Shared\Event\EntityActionEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ConsultationNotificationAdapter implements ConsultationNotificationPort
{
    public function __construct(
        private readonly ConsultationNotificationService $consultationNotificationService,
        private readonly NotificationRecipientResolver $notificationRecipientResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function notifyCreation(object $consultation, ?object $triggeredBy): void
    {
        if (!$consultation instanceof Consultation) {
            return;
        }

        $emitter = $triggeredBy instanceof User ? $triggeredBy : null;
        $this->consultationNotificationService->notifyCreation($consultation, $emitter);
    }

    public function notifyReceptionOnClosure(object $consultation, float $invoiceAmount): void
    {
        if (!$consultation instanceof Consultation) {
            return;
        }

        $recipients = $this->notificationRecipientResolver->receptionists();
        if ($recipients === []) {
            return;
        }

        $patient = $consultation->getPatient();
        $patientName = trim($patient?->getFullName() ?? '') ?: 'un patient';
        $amountLabel = number_format($invoiceAmount, 0, ',', ' ');
        $message = sprintf(
            'Consultation de %s clôturée : facture de %s FCFA prête en caisse.',
            $patientName,
            $amountLabel,
        );

        $this->eventDispatcher->dispatch(
            new EntityActionEvent(
                $consultation,
                'closed',
                ['ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE'],
                null,
                [
                    'message' => $message,
                    'priority' => 'info',
                    'type' => 'success',
                    'link' => '/reception/caisse',
                ],
            )
        );
    }

    public function notifyCancelled(object $consultation, ?object $actor): void
    {
        if (!$consultation instanceof Consultation) {
            return;
        }

        $emitter = $actor instanceof User ? $actor : null;

        $this->eventDispatcher->dispatch(
            new EntityActionEvent(
                $consultation,
                'cancelled',
                ['ROLE_MEDECIN'],
                $emitter,
                [
                    'priority' => 'warning',
                    'type' => 'warning',
                    'link' => '/consultations',
                ],
            )
        );
    }
}
