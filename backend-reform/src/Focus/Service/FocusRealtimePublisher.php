<?php

namespace App\Focus\Service;

use App\Billing\Entity\Devis;
use App\CareDelivery\Entity\Consultation;
use App\Communication\Mercure\NotificationTopicGenerator;
use App\Patient\Entity\Patient;
use App\IdentityAccess\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class FocusRealtimePublisher
{
    private const TARGET_ROLES = [
        'ROLE_ADMIN',
        'ROLE_RECEPTION',
        'ROLE_RECEPTIONNISTE',
        'ROLE_SECRETAIRE',
        'ROLE_MEDECIN',
    ];

    public function __construct(
        private readonly HubInterface $hub,
        private readonly NotificationTopicGenerator $topicGenerator,
        private readonly UserRepository $userRepository,
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

    public function publishDevisRefresh(Devis $devis, string $action = 'updated'): void
    {
        $devisId = $devis->getId();
        if ($devisId === null) {
            return;
        }

        $consultation = $devis->getConsultation();
        $patient = $consultation?->getPatient() ?? $devis->getFicheMedicale()?->getPatient() ?? $devis->getFiche()?->getPatient();

        $payload = [
            'entity' => 'devis',
            'action' => $action,
            'devisId' => $devisId,
            'consultationId' => $consultation?->getId(),
            'patientId' => $patient?->getId(),
            'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $this->publishPayload(
            $payload,
            sprintf('focus-devis-%s-%d', $action, $devisId),
            'focus-devis',
            [
                'devisId' => $devisId,
                'action' => $action,
            ]
        );
    }

    private function publishPayload(array $payload, string $updateId, string $eventName, array $logContext = []): void
    {
        $users = [];
        foreach ($this->userRepository->findByRoles(self::TARGET_ROLES) as $user) {
            $userId = $user->getId() ?? spl_object_id($user);
            $users[$userId] = $user;
        }

        foreach ($users as $user) {
            $topic = $this->topicGenerator->forUser($user);
            if ($topic === null) {
                continue;
            }

            try {
                $update = new Update(
                    $topic,
                    json_encode($payload, JSON_THROW_ON_ERROR),
                    false,
                    $updateId,
                    $eventName
                );

                $this->hub->publish($update);
            } catch (\Throwable $exception) {
                $this->logger->warning('Impossible de publier la mise a jour Focus sur Mercure.', [
                    'exception' => $exception,
                    'userId' => $user->getId(),
                ] + $logContext);
            }
        }
    }
}