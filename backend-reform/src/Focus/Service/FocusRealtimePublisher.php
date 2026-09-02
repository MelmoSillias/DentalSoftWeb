<?php

namespace App\Focus\Service;

use App\Billing\Entity\Devis;
use App\Billing\Entity\Facture;
use App\Billing\Entity\ModeDePaiement;
use App\CareDelivery\Entity\Consultation;
use App\Communication\Mercure\NotificationTopicGenerator;
use App\Communication\Mercure\RealtimeEnvelope;
use App\IdentityAccess\Entity\Employe;
use App\Patient\Entity\Patient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\MessageBusInterface;

final class FocusRealtimePublisher
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly NotificationTopicGenerator $topicGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publishConsultationRefresh(Consultation $consultation, string $action = 'updated'): void
    {
        $consultationId = $consultation->getId();
        if ($consultationId === null) {
            return;
        }

        $payload = [
            'entity' => 'consultation',
            'action' => $action,
            'consultationId' => $consultationId,
            'patientId' => $consultation->getPatient()?->getId(),
            'medecinId' => $consultation->getMedecin()?->getId(),
            'state' => $consultation->getStatut(),
            'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $this->publishPayload(
            $payload,
            sprintf('focus-consultation-%s-%d', $action, $consultationId),
            'focus-consultation',
            [
                'consultationId' => $consultationId,
                'action' => $action,
            ]
        );
    }

    public function publishPatientRefresh(Patient $patient, string $action = 'updated'): void
    {
        $patientId = $patient->getId();
        if ($patientId === null) {
            return;
        }

        $payload = [
            'entity' => 'patient',
            'action' => $action,
            'patientId' => $patientId,
            'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $this->publishPayload(
            $payload,
            sprintf('focus-patient-%s-%d', $action, $patientId),
            'focus-patient',
            [
                'patientId' => $patientId,
                'action' => $action,
            ]
        );
    }

    public function publishFactureRefresh(Facture $facture, string $action = 'updated'): void
    {
        $factureId = $facture->getId();
        if ($factureId === null) {
            return;
        }

        $consultation = $facture->getConsultation();
        $patient = $consultation?->getPatient();

        $payload = [
            'entity' => 'facture',
            'action' => $action,
            'factureId' => $factureId,
            'consultationId' => $consultation?->getId(),
            'patientId' => $patient?->getId(),
            'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $this->publishPayload(
            $payload,
            sprintf('focus-facture-%s-%d', $action, $factureId),
            'focus-facture',
            [
                'factureId' => $factureId,
                'action' => $action,
            ]
        );
    }

    public function publishMedecinRefresh(Employe $medecin, string $action = 'updated'): void
    {
        $medecinId = $medecin->getId();
        if ($medecinId === null) {
            return;
        }

        $payload = [
            'entity' => 'medecin',
            'action' => $action,
            'medecinId' => $medecinId,
            'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $this->publishPayload(
            $payload,
            sprintf('focus-medecin-%s-%d', $action, $medecinId),
            'focus-medecin',
            [
                'medecinId' => $medecinId,
                'action' => $action,
            ]
        );
    }

    public function publishPaymentMethodRefresh(ModeDePaiement $method, string $action = 'updated'): void
    {
        $methodId = $method->getId();
        if ($methodId === null) {
            return;
        }

        $payload = [
            'entity' => 'payment_method',
            'action' => $action,
            'paymentMethodId' => $methodId,
            'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $this->publishPayload(
            $payload,
            sprintf('focus-payment-method-%s-%d', $action, $methodId),
            'focus-payment-method',
            [
                'paymentMethodId' => $methodId,
                'action' => $action,
            ]
        );
    }

  /**
   * @param array<string, mixed> $payload
   * @param array<string, mixed> $logContext
   */
    private function publishPayload(array $payload, string $updateId, string $eventName, array $logContext = []): void
    {
        $topic = $this->topicGenerator->forFocusChannel();

        try {
            $update = new Update(
                $topic,
                RealtimeEnvelope::focus($payload, $updateId, $eventName),
                true,
                $updateId,
                $eventName
            );

            $this->bus->dispatch($update);
        } catch (\Throwable $exception) {
            $this->logger->warning('Impossible d\'enfiler la mise a jour Focus Mercure.', [
                'exception' => $exception,
            ] + $logContext);
        }
    }
}
