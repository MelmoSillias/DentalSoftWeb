<?php

namespace App\Service;

use App\Entity\Consultation;
use App\Entity\Notification;
use App\Entity\User;

final class ConsultationNotificationService
{
    public function __construct(private readonly NotificationService $notificationService)
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
        $recipients = $this->collectRecipients($consultation);
        if ($recipients === []) {
            return;
        }

        $patient = $consultation->getPatient();
        $patientName = trim(sprintf('%s %s', $patient?->getNom() ?? '', $patient?->getPrenom() ?? '')) ?: 'un patient';
        $dateLabel = $consultation->getCreatedAt()?->format('d/m/Y H:i');

        if ($event === 'creation') {
            $message = sprintf(
                'Nouvelle consultation planifiée pour %s%s.',
                $patientName,
                $dateLabel ? ' le ' . $dateLabel : ''
            );
            $priority = Notification::PRIORITY_INFO;
            $type = Notification::TYPE_SUCCESS;
        } else {
            $message = sprintf('Consultation de %s annulée.', $patientName);
            $priority = Notification::PRIORITY_WARNING;
            $type = Notification::TYPE_WARNING;
        }

        $link = $consultation->getId()
            ? sprintf('/medecin/consultation/%d/details', $consultation->getId())
            : '/medecin/consultation/en-attente';

        $this->notificationService->notifyMany(
            $recipients,
            $message,
            $priority,
            $link,
            $type,
            $emitter,
        );
    }

    /**
     * @return list<User>
     */
    private function collectRecipients(Consultation $consultation): array
    {
        $users = [];

        foreach ([$consultation->getMedecin()?->getUser(), $consultation->getInfirmier()?->getUser()] as $user) {
            if ($user instanceof User) {
                $users[$user->getId() ?? spl_object_id($user)] = $user;
            }
        }

        return array_values($users);
    }
}
