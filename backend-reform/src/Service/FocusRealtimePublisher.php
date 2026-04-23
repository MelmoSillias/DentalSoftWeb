<?php

namespace App\Service;

use App\CareDelivery\Entity\Consultation;
use App\Mercure\NotificationTopicGenerator;
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
                    sprintf('focus-consultation-%s-%d', $action, $consultationId),
                    'focus-consultation'
                );

                $this->hub->publish($update);
            } catch (\Throwable $exception) {
                $this->logger->warning('Impossible de publier la mise a jour Focus sur Mercure.', [
                    'exception' => $exception,
                    'consultationId' => $consultationId,
                    'action' => $action,
                    'userId' => $user->getId(),
                ]);
            }
        }
    }
}