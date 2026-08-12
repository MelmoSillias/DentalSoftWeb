<?php

namespace App\Communication\EventSubscriber;

use App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\Conge;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\Shared\Event\EntityActionEvent;
use App\Inventory\Infrastructure\Persistence\Doctrine\Entity\Consommable;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Patient;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\Rdv;
use App\Communication\Service\NotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class NotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityActionEvent::class => 'onEntityAction',
        ];
    }

    public function onEntityAction(EntityActionEvent $event): void
    {
        $roles = $event->getTargetRoles();
        if ($roles === []) {
            return;
        }

        $payload = $this->buildPayload($event);
        if ($payload === null) {
            return;
        }

        $context = $event->getContext();

        $this->notificationService->notifyRoles(
            $roles,
            $payload['message'],
            $payload['link'] ?? null,
            $payload['priority'],
            $event->getEmitter(),
            $event->getEntity(),
            $context,
            $payload['type'] ?? null,
        );
    }

    /**
     * @return array{message: string, priority: string, type: string, link: ?string}|null
     */
    private function buildPayload(EntityActionEvent $event): ?array
    {
        $entity = $event->getEntity();
        $action = $event->getActionType();
        $context = $event->getContext();

        $priority = (string) ($context['priority'] ?? 'info');
        $type = (string) ($context['type'] ?? 'info');
        $link = isset($context['link']) && is_string($context['link']) ? $context['link'] : null;

        if (isset($context['message']) && is_string($context['message']) && $context['message'] !== '') {
            return [
                'message' => $context['message'],
                'priority' => $priority,
                'type' => $type,
                'link' => $link,
            ];
        }

        if ($entity instanceof Patient && $action === 'created') {
            $patientName = trim($entity->getFullName() ?? '') ?: 'un patient';

            return [
                'message' => sprintf('Nouveau patient ajouté : %s.', $patientName),
                'priority' => $priority,
                'type' => $type,
                'link' => $link ?? '/reception/patients',
            ];
        }

        if ($entity instanceof Consultation) {
            $patientName = trim($entity->getPatient()?->getFullName() ?? '') ?: 'un patient';
            $message = $action === 'cancelled'
                ? sprintf('Consultation annulée pour %s.', $patientName)
                : sprintf('Nouvelle consultation pour %s.', $patientName);

            return [
                'message' => $message,
                'priority' => $priority,
                'type' => $type,
                'link' => $link ?? ($entity->getId() ? sprintf('/consultations/%d', $entity->getId()) : '/consultations'),
            ];
        }

        if ($entity instanceof Rdv) {
            $patientName = trim($entity->getPatient()?->getFullName() ?? '') ?: 'un patient';
            $dateLabel = $entity->getDateRdv()?->format('d/m/Y H:i') ?? 'date à confirmer';

            $message = match ($action) {
                'validated' => sprintf('Rendez-vous validé pour %s (%s).', $patientName, $dateLabel),
                'cancelled' => sprintf('Rendez-vous annulé pour %s (%s).', $patientName, $dateLabel),
                'reported' => sprintf('Rendez-vous reporté pour %s.', $patientName),
                default => sprintf('Nouveau rendez-vous pour %s (%s).', $patientName, $dateLabel),
            };

            return [
                'message' => $message,
                'priority' => $priority,
                'type' => $type,
                'link' => $link ?? '/agenda/rendez-vous',
            ];
        }

        if ($entity instanceof Conge) {
            $employeeName = $entity->getEmploye()?->getFullName() ?? 'Un employé';
            $start = $entity->getStartDate()?->format('d/m/Y') ?? 'date inconnue';
            $end = $entity->getEndDate()?->format('d/m/Y') ?? 'date inconnue';

            return [
                'message' => sprintf('Congé %s pour %s du %s au %s.', $action, $employeeName, $start, $end),
                'priority' => $priority,
                'type' => $type,
                'link' => $link ?? '/admin/agenda/jours-conges',
            ];
        }

        if ($entity instanceof Consommable) {
            $quantity = $entity->getQuantity();
            $threshold = $entity->getLowValue();

            return [
                'message' => sprintf('Consommable %s (%s) — stock %d, seuil %d.', $action, $entity->getNom(), $quantity, $threshold),
                'priority' => $priority,
                'type' => $type,
                'link' => $link ?? '/admin/consommables',
            ];
        }

        if ($entity instanceof User) {
            return [
                'message' => sprintf('Compte utilisateur %s : %s.', $entity->getUsername(), $action),
                'priority' => $priority,
                'type' => $type,
                'link' => $link ?? '/admin/users',
            ];
        }

        return null;
    }
}
