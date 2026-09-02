<?php

namespace App\IdentityAccess\Service;

use App\Communication\Entity\Notification;
use App\Communication\Repository\NotificationRepository;
use App\IdentityAccess\Entity\User;
use App\IdentityAccess\Repository\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher, 
        private Security $security,
        private NotificationRepository $notificationRepository,
        private EmployeRepository $employeRepository,
        private RequestStack $requestStack,
    ) {
    }

    /** @return array<string,mixed> */
    public function register(array $data): array
    {
        $username = $data['username'] ?? ($data['email'] ?? null);
        $password = $data['password'] ?? null;

        if (!$username || !$password) {
            throw new BadRequestHttpException('username and password required');
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($existing) {
            throw new BadRequestHttpException('User already exists');
        }

        $user = new User();
        $user->setUsername($username);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        return ['message' => 'user created'];
    }

    /** @return array<string,mixed> */
    public function me(): array
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user) {
            throw new NotFoundHttpException('User not authenticated');
        }

        $employee = $this->employeRepository->findOneBy(['user' => $user]);
        $notifications = $this->notificationRepository->findLatestForUser($user, 20);
        $unreadCount = $this->notificationRepository->countUnread($user);
        if (!$user->isNotificationsEnabled()) {
            $notifications = [];
            $unreadCount = 0;
        }
        $activity = $this->getActivityFeed($user);

        return [
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'roles' => $user->getRoles(),
                'notificationsEnabled' => $user->isNotificationsEnabled(),
            ],
            'employee' => $employee ? [
                'id' => $employee->getId(),
                'nom' => $employee->getNom(),
                'prenom' => $employee->getPrenom(),
                'telephone' => $employee->getTelephone(),
                'email' => $employee->getEmail(),
                'fonction' => $employee->getFonction(),
                'matricule' => $employee->getMatricule(),
                'type' => $employee->getType(),
                'typeContrat' => $employee->getTypeContrat(),
                'dateEmbauche' => $employee->getDateEmbauche()?->format('Y-m-d'),
            ] : null,
            'notifications' => array_map(fn(Notification $n) => $this->mapNotification($n), $notifications),
            'notificationsUnreadCount' => $unreadCount,
            'activity' => $activity,
            'stats' => [
                'accountStatus' => 'Actif',
                'notificationsCount' => count($notifications),
                'activityCount' => count($activity),
                'lastAccess' => $activity[0]['time'] ?? null,
            ],
        ];
    }

    public function updateMe(array $data, ?UploadedFile $file, string $uploadDir): array
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user) {
            throw new NotFoundHttpException('User not authenticated');
        }

        if (isset($data['username']) && is_string($data['username']) && $data['username'] !== '') {
            $user->setUsername($data['username']);
        }

        if (array_key_exists('notificationsEnabled', $data)) {
            $user->setNotificationsEnabled((bool) $data['notificationsEnabled']);
        }

        $employee = $this->employeRepository->findOneBy(['user' => $user]);
        if ($employee) {
            if (array_key_exists('nom', $data)) {
                $employee->setNom((string) $data['nom']);
            }
            if (array_key_exists('prenom', $data)) {
                $employee->setPrenom((string) $data['prenom']);
            }
            if (array_key_exists('telephone', $data)) {
                $employee->setTelephone($data['telephone'] ? (string) $data['telephone'] : null);
            }
            if (array_key_exists('email', $data)) {
                $employee->setEmail($data['email'] ? (string) $data['email'] : null);
            }
            if (array_key_exists('fonction', $data)) {
                $employee->setFonction((string) $data['fonction']);
            }
            if (array_key_exists('matricule', $data)) {
                $employee->setMatricule($data['matricule'] ? (string) $data['matricule'] : null);
            }
            if (array_key_exists('type', $data)) {
                $employee->setType((string) $data['type']);
            }
            if (array_key_exists('typeContrat', $data)) {
                $employee->setTypeContrat((string) $data['typeContrat']);
            }
        }

        $this->em->flush();

        return ['status' => 'ok'];
    }

    /** @return array<int, array<string, mixed>> */
    private function getActivityFeed(User $user): array
    {
        $session = $this->requestStack->getSession();
        $key = sprintf('user_activity_feed_%d', $user->getId());
        $raw = $session?->get($key, []);

        if (!is_array($raw)) {
            return [];
        }

        return array_map(function (array $entry) {
            $timestamp = $entry['timestamp'] ?? null;
            $time = null;
            if (is_string($timestamp)) {
                try {
                    $time = (new \DateTimeImmutable($timestamp))->format('Y-m-d H:i');
                } catch (\Exception) {
                    $time = null;
                }
            }

            return [
                'title' => $entry['title'] ?? 'Activité',
                'subtitle' => $entry['subtitle'] ?? '',
                'icon' => $entry['icon'] ?? 'pi pi-info-circle',
                'badge' => $entry['badge'] ?? 'secondary',
                'ip' => $entry['ip'] ?? null,
                'userAgent' => $entry['userAgent'] ?? null,
                'time' => $time,
            ];
        }, $raw);
    }

    /** @return array<string, mixed> */
    private function mapNotification(Notification $notification): array
    {
        return [
            'id' => $notification->getId(),
            'message' => $notification->getMessage(),
            'status' => $notification->getEtatVu(),
            'priority' => $notification->getPriority(),
            'type' => $notification->getType(),
            'createdAt' => $notification->getDateEnvoi()?->format('Y-m-d H:i'),
            'link' => $notification->getLink(),
        ];
    }

    /** @return array<string,mixed> */
    public function changePassword(array $data): array
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user) {
            throw new NotFoundHttpException('User not authenticated');
        }

        $old = $data['oldPassword'] ?? null;
        $new = $data['newPassword'] ?? null;
        if (!$old || !$new) {
            throw new BadRequestHttpException('oldPassword and newPassword required');
        }

        if (!$this->hasher->isPasswordValid($user, $old)) {
            throw new BadRequestHttpException('Invalid old password');
        }

        $user->setPassword($this->hasher->hashPassword($user, $new));
        $this->em->flush();

        return ['status' => 'ok'];
    }

    // /** @return array<int,array<string,mixed>> */
    // public function myLogs(): array
    // {
    //     /** @var User|null $user */
    //     $user = $this->security->getUser();
    //     if (!$user) {
    //         throw new NotFoundHttpException('User not authenticated');
    //     }

    //     // $logs = $this->logRepository->findBy(['user' => $user], ['createdAt' => 'DESC'], 50);

    //     return array_map(static fn($log) => [
    //         'id' => $log->getId(),
    //         'action' => $log->getAction(),
    //         'details' => $log->getDetails(),
    //         'createdAt' => $log->getCreatedAt()->format('Y-m-d H:i:s')
    //     ], $logs);
    // }

    /** @return array<string,mixed> */
    public function validateToken(): array
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user) {
            return [
                'status' => 'invalid',
                'message' => 'Unauthorized',
            ];
        }

        return [
            'status' => 'valid',
            'user' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ];
    }
}
