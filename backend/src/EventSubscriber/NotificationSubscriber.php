<?php

namespace App\EventSubscriber;

use App\Entity\Conge;
use App\Entity\Consultation;
use App\Entity\Consommable;
use App\Entity\Patient;
use App\Entity\Rdv;
use App\Entity\User;
use App\Enum\NotificationPriority;
use App\Service\NotificationService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs as EventLifecycleEventArgs;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;
use Symfony\Component\Security\Core\Security;

class NotificationSubscriber implements EventSubscriber
{
    public function __construct(
        private NotificationService $notificationService,
        private SecurityBundleSecurity $security,
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
        ];
    }

    public function postPersist(EventLifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Patient) {
            $actor = $this->currentUser();
            $fullName = trim(($entity->getNom() ?? '') . ' ' . ($entity->getPrenom() ?? ''));
            $message = sprintf('Nouveau patient %s ajouté%s.', $fullName, $actor ? ' par ' . $actor->getUsername() : '');
            $this->notificationService->notifyRoles(
                ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE'],
                $message,
                '/patients/' . $entity->getId(),
                NotificationPriority::INFO,
                $actor,
                $entity,
                null
            );
        }

        if ($entity instanceof Consultation) {
            $medecinUser = $entity->getMedecin()?->getUser();
            if ($medecinUser instanceof User) {
                $message = sprintf('Nouvelle consultation pour %s.', $entity->getPatient()?->getFullName() ?? 'patient');
                $this->notificationService->notifyUsers([
                    $medecinUser,
                ], $message, '/consultations/' . $entity->getId(), NotificationPriority::INFO, $this->currentUser(), $entity->getPatient(), $entity);
            }
        }

        if ($entity instanceof Rdv) {
            $medecinUser = $entity->getMedecin()?->getUser();
            if ($medecinUser instanceof User) {
                $message = sprintf('Nouveau rendez-vous fixé pour %s le %s.', $entity->getPatient()?->getFullName() ?? 'patient', $entity->getDateRdv()?->format('d/m/Y H:i') ?? '');
                $this->notificationService->notifyUsers([
                    $medecinUser,
                ], $message, '/rdv/' . $entity->getId(), NotificationPriority::INFO, $this->currentUser(), $entity->getPatient(), null);
            }
        }

        if ($entity instanceof Conge) {
            $user = $entity->getEmploye()?->getUser();
            if ($user instanceof User) {
                $message = sprintf('Nouveau congé %s du %s au %s.', $entity->getType(), $entity->getStartDate()?->format('d/m/Y') ?? '', $entity->getEndDate()?->format('d/m/Y') ?? '');
                $this->notificationService->notifyUsers([
                    $user,
                ], $message, '/conges/' . $entity->getId(), NotificationPriority::INFO, $this->currentUser());
            }
        }

        if ($entity instanceof Consommable && $entity->onLowValue()) {
            $message = sprintf('Stock faible: %s (quantité %d, seuil %d).', $entity->getNom(), $entity->getQuantity(), $entity->getLowValue());
            $this->notificationService->notifyRoles(['ROLE_ADMIN'], $message, '/consommables/' . $entity->getId(), NotificationPriority::AVERTISSEMENT, $this->currentUser());
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Rdv) {
            $medecinUser = $entity->getMedecin()?->getUser();
            if ($medecinUser instanceof User) {
                $message = sprintf('Mise à jour du rendez-vous de %s (%s).', $entity->getPatient()?->getFullName() ?? 'patient', $entity->getDateRdv()?->format('d/m/Y H:i') ?? '');
                $this->notificationService->notifyUsers([
                    $medecinUser,
                ], $message, '/rdv/' . $entity->getId(), NotificationPriority::INFO, $this->currentUser(), $entity->getPatient(), null);
            }
        }

        if ($entity instanceof Consultation) {
            $medecinUser = $entity->getMedecin()?->getUser();
            if ($medecinUser instanceof User) {
                $message = sprintf('Consultation mise à jour pour %s.', $entity->getPatient()?->getFullName() ?? 'patient');
                $this->notificationService->notifyUsers([
                    $medecinUser,
                ], $message, '/consultations/' . $entity->getId(), NotificationPriority::INFO, $this->currentUser(), $entity->getPatient(), $entity);
            }
        }

        if ($entity instanceof Conge) {
            $user = $entity->getEmploye()?->getUser();
            if ($user instanceof User) {
                $message = sprintf('Mise à jour congé %s (%s - %s).', $entity->getType(), $entity->getStartDate()?->format('d/m/Y') ?? '', $entity->getEndDate()?->format('d/m/Y') ?? '');
                $this->notificationService->notifyUsers([
                    $user,
                ], $message, '/conges/' . $entity->getId(), NotificationPriority::INFO, $this->currentUser());
            }
        }

        if ($entity instanceof Consommable && $entity->onLowValue()) {
            $message = sprintf('Stock faible: %s (quantité %d, seuil %d).', $entity->getNom(), $entity->getQuantity(), $entity->getLowValue());
            $this->notificationService->notifyRoles(['ROLE_ADMIN'], $message, '/consommables/' . $entity->getId(), NotificationPriority::AVERTISSEMENT, $this->currentUser());
        }
    }

    public function postRemove(EventLifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Consultation) {
            $medecinUser = $entity->getMedecin()?->getUser();
            if ($medecinUser instanceof User) {
                $message = sprintf('Consultation supprimée pour %s.', $entity->getPatient()?->getFullName() ?? 'patient');
                $this->notificationService->notifyUsers([
                    $medecinUser,
                ], $message, '/consultations', NotificationPriority::INFO, $this->currentUser(), $entity->getPatient(), $entity);
            }
        }

        if ($entity instanceof Rdv) {
            $medecinUser = $entity->getMedecin()?->getUser();
            if ($medecinUser instanceof User) {
                $message = sprintf('Rendez-vous annulé pour %s.', $entity->getPatient()?->getFullName() ?? 'patient');
                $this->notificationService->notifyUsers([
                    $medecinUser,
                ], $message, '/rdv', NotificationPriority::INFO, $this->currentUser(), $entity->getPatient(), null);
            }
        }

        if ($entity instanceof Conge) {
            $user = $entity->getEmploye()?->getUser();
            if ($user instanceof User) {
                $message = sprintf('Congé %s supprimé (%s - %s).', $entity->getType(), $entity->getStartDate()?->format('d/m/Y') ?? '', $entity->getEndDate()?->format('d/m/Y') ?? '');
                $this->notificationService->notifyUsers([
                    $user,
                ], $message, '/conges', NotificationPriority::INFO, $this->currentUser());
            }
        }
    }

    private function currentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
