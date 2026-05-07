<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserDevice;
use App\Entity\UserDeviceAccessLog;
use App\Repository\UserDeviceAccessLogRepository;
use App\Repository\UserDeviceRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class UserDeviceService
{
    private const HEARTBEAT_INTERVAL_SECONDS = 30;

    public function __construct(
        private UserDeviceRepository $userDeviceRepo,
        private UserDeviceAccessLogRepository $accessLogRepo,
        private UserRepository $userRepo,
        private EntityManagerInterface $em,
        private NotificationService $notificationService,
        private NotificationRecipientResolver $recipientResolver,
        private GlobalSettingsService $globalSettingsService,
        private int $maxDevicesPerUser = 2,
    ) {
    }

    public function getMaxDevicesPerUser(): int
    {
        return max(1, $this->maxDevicesPerUser);
    }

    /** @return array{id:string,name:string,type:string,userAgent:?string,ip:?string} */
    public function resolveRequestDevice(Request $request): array
    {
        $userAgent = $request->headers->get('User-Agent');
        $ip = $request->getClientIp();

        $deviceId = trim((string) $request->headers->get('X-Device-Id', ''));
        if ($deviceId === '') {
            $deviceId = hash('sha256', (string) ($userAgent . '|' . $request->headers->get('Accept-Language', '')));
        }

        $deviceName = trim((string) $request->headers->get('X-Device-Name', ''));
        if ($deviceName === '') {
            $deviceName = $userAgent ? mb_substr($userAgent, 0, 120) : 'Appareil inconnu';
        }

        $deviceType = strtolower(trim((string) $request->headers->get('X-Device-Type', 'desktop')));
        if (!in_array($deviceType, ['desktop', 'mobile', 'tablet', 'browser', 'other'], true)) {
            $deviceType = 'other';
        }

        return [
            'id' => mb_substr($deviceId, 0, 128),
            'name' => mb_substr($deviceName, 0, 255),
            'type' => $deviceType,
            'userAgent' => $userAgent,
            'ip' => $ip,
        ];
    }

    /** @return array{allowed:bool,code:int,message:?string,device:UserDevice} */
    public function enforceDeviceForRequest(User $user, Request $request): array
    {
        $context = $this->resolveRequestDevice($request);

        $device = $this->userDeviceRepo->findOneByUserAndIdentifier($user, $context['id']);
        $now = new \DateTimeImmutable();

        if (!$device) {
            $autoApproveEnabled = $this->globalSettingsService->isAutoApproveDevicesEnabled();
            $isBootstrapAdminDevice = in_array('ROLE_ADMIN', $user->getRoles(), true)
                && $this->userDeviceRepo->countApprovedByUser($user) === 0;
            $shouldAutoApprove = $autoApproveEnabled || $isBootstrapAdminDevice;

            $device = (new UserDevice())
                ->setUser($user)
                ->setDeviceIdentifier($context['id'])
                ->setDeviceName($context['name'])
                ->setDeviceType($context['type'])
                ->setUserAgent($context['userAgent'])
                ->setIpAddress($context['ip'])
                ->setRequestedAt($now)
                ->setLastSeenAt($now)
                ->setStatus($shouldAutoApprove ? UserDevice::STATUS_APPROVED : UserDevice::STATUS_PENDING)
                ->setValidatedAt($shouldAutoApprove ? $now : null);

            $this->em->persist($device);
            $this->logAccess($user, $device, $request, $shouldAutoApprove ? 'allowed' : UserDevice::STATUS_PENDING);
            $this->flushWithRetry();

            if (!$shouldAutoApprove) {
                $this->notifyNewDeviceRequest($user, $device);
            }

            if ($shouldAutoApprove) {
                return [
                    'allowed' => true,
                    'code' => 200,
                    'message' => null,
                    'device' => $device,
                ];
            }

            return [
                'allowed' => false,
                'code' => 403,
                'message' => 'Nouvel appareil detecte. Demande envoyee a un administrateur pour validation.',
                'device' => $device,
            ];
        }

        if ($device->getStatus() === UserDevice::STATUS_APPROVED) {
            if (!$this->shouldPersistHeartbeat($request, $device, $context, $now)) {
                return [
                    'allowed' => true,
                    'code' => 200,
                    'message' => null,
                    'device' => $device,
                ];
            }

            $device
                ->setDeviceName($context['name'])
                ->setDeviceType($context['type'])
                ->setUserAgent($context['userAgent'])
                ->setIpAddress($context['ip'])
                ->setLastSeenAt($now);

            $this->logAccess($user, $device, $request, 'allowed');
            $this->flushWithRetry();

            return [
                'allowed' => true,
                'code' => 200,
                'message' => null,
                'device' => $device,
            ];
        }

        $device
            ->setDeviceName($context['name'])
            ->setDeviceType($context['type'])
            ->setUserAgent($context['userAgent'])
            ->setIpAddress($context['ip'])
            ->setLastSeenAt($now);

        $this->logAccess($user, $device, $request, $device->getStatus());
        $this->flushWithRetry();

        $message = $device->getStatus() === UserDevice::STATUS_REJECTED
            ? 'Cet appareil a ete refuse. Contactez un administrateur.'
            : 'Cet appareil est en attente de validation par un administrateur.';

        return [
            'allowed' => false,
            'code' => 403,
            'message' => $message,
            'device' => $device,
        ];
    }

    /** @return array<int, array<string,mixed>> */
    public function listDevicesForUser(int $userId): array
    {
        $user = $this->userRepo->find($userId);
        if (!$user) {
            return [];
        }

        return array_map(fn(UserDevice $device) => $this->serializeDevice($device), $this->userDeviceRepo->findByUserOrdered($user));
    }

    public function approveDevice(int $userId, int $deviceId, ?User $admin): array
    {
        $user = $this->userRepo->find($userId);
        if (!$user) {
            return ['error' => 'Utilisateur introuvable', 'status' => 404];
        }

        $device = $this->userDeviceRepo->find($deviceId);
        if (!$device || $device->getUser()?->getId() !== $user->getId()) {
            return ['error' => 'Appareil introuvable', 'status' => 404];
        }

        $approvedCount = $this->userDeviceRepo->countApprovedByUser($user);
        $isAlreadyApproved = $device->getStatus() === UserDevice::STATUS_APPROVED;

        if (!$isAlreadyApproved && $approvedCount >= $this->getMaxDevicesPerUser()) {
            return [
                'error' => sprintf('Limite atteinte: maximum %d appareils autorises.', $this->getMaxDevicesPerUser()),
                'status' => 400,
            ];
        }

        $device
            ->setStatus(UserDevice::STATUS_APPROVED)
            ->setValidatedAt(new \DateTimeImmutable())
            ->setValidatedBy($admin)
            ->setRejectionReason(null);

        $this->em->flush();

        $this->notificationService->notify(
            $user,
            sprintf('Votre appareil "%s" a ete approuve.', $device->getDeviceName() ?? 'Inconnu'),
            'info',
            '/profile',
            'success',
            $admin,
        );

        return ['success' => true, 'device' => $this->serializeDevice($device)];
    }

    public function rejectDevice(int $userId, int $deviceId, ?User $admin): array
    {
        $user = $this->userRepo->find($userId);
        if (!$user) {
            return ['error' => 'Utilisateur introuvable', 'status' => 404];
        }

        $device = $this->userDeviceRepo->find($deviceId);
        if (!$device || $device->getUser()?->getId() !== $user->getId()) {
            return ['error' => 'Appareil introuvable', 'status' => 404];
        }

        $device
            ->setStatus(UserDevice::STATUS_REJECTED)
            ->setValidatedAt(new \DateTimeImmutable())
            ->setValidatedBy($admin);

        $this->em->flush();

        $this->notificationService->notify(
            $user,
            sprintf('Votre appareil "%s" a ete refuse.', $device->getDeviceName() ?? 'Inconnu'),
            'warning',
            '/profile',
            'warning',
            $admin,
        );

        return ['success' => true, 'device' => $this->serializeDevice($device)];
    }

    public function deleteDevice(int $userId, int $deviceId): array
    {
        $user = $this->userRepo->find($userId);
        if (!$user) {
            return ['error' => 'Utilisateur introuvable', 'status' => 404];
        }

        $device = $this->userDeviceRepo->find($deviceId);
        if (!$device || $device->getUser()?->getId() !== $user->getId()) {
            return ['error' => 'Appareil introuvable', 'status' => 404];
        }

        $this->em->remove($device);
        $this->em->flush();

        return ['success' => true];
    }

    /** @return array<int, array<string,mixed>> */
    public function getUserAccessLogs(int $userId, int $limit = 50): array
    {
        $user = $this->userRepo->find($userId);
        if (!$user) {
            return [];
        }

        $logs = $this->accessLogRepo->findLatestByUser($user, max(1, min(200, $limit)));

        return array_map(static fn(UserDeviceAccessLog $log): array => [
            'id' => $log->getId(),
            'path' => $log->getPath(),
            'status' => $log->getStatus(),
            'ipAddress' => $log->getIpAddress(),
            'userAgent' => $log->getUserAgent(),
            'createdAt' => $log->getCreatedAt()?->format(DATE_ATOM),
            'deviceId' => $log->getDevice()?->getId(),
            'deviceName' => $log->getDevice()?->getDeviceName(),
        ], $logs);
    }

    private function notifyNewDeviceRequest(User $user, UserDevice $device): void
    {
        $deviceName = $device->getDeviceName() ?: 'Appareil inconnu';

        $this->notificationService->notify(
            $user,
            sprintf('Connexion detectee sur un nouvel appareil "%s". En attente de validation.', $deviceName),
            'warning',
            '/profile',
            'warning',
            null,
        );

        $admins = $this->recipientResolver->admins();
        if ($admins !== []) {
            $this->notificationService->notifyMany(
                $admins,
                sprintf('Nouvelle demande d appareil pour %s (%s).', $user->getUsername(), $deviceName),
                'warning',
                '/administration/utilisateurs',
                'warning',
                null
            );
        }
    }

    private function logAccess(User $user, ?UserDevice $device, Request $request, string $status): void
    {
        $log = (new UserDeviceAccessLog())
            ->setUser($user)
            ->setDevice($device)
            ->setPath((string) $request->getPathInfo())
            ->setIpAddress($request->getClientIp())
            ->setUserAgent($request->headers->get('User-Agent'))
            ->setStatus($status)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($log);
    }

    /** @param array{id:string,name:string,type:string,userAgent:?string,ip:?string} $context */
    private function shouldPersistHeartbeat(Request $request, UserDevice $device, array $context, \DateTimeImmutable $now): bool
    {
        if (!$request->isMethodCacheable()) {
            return true;
        }

        if (($device->getIpAddress() ?? '') !== ($context['ip'] ?? '')) {
            return true;
        }

        if (($device->getUserAgent() ?? '') !== ($context['userAgent'] ?? '')) {
            return true;
        }

        if (($device->getDeviceName() ?? '') !== ($context['name'] ?? '')) {
            return true;
        }

        if (($device->getDeviceType() ?? '') !== ($context['type'] ?? '')) {
            return true;
        }

        $lastSeen = $device->getLastSeenAt();
        if (!$lastSeen instanceof \DateTimeInterface) {
            return true;
        }

        return ($now->getTimestamp() - $lastSeen->getTimestamp()) >= self::HEARTBEAT_INTERVAL_SECONDS;
    }

    private function flushWithRetry(): void
    {
        for ($attempt = 1; $attempt <= 2; ++$attempt) {
            try {
                $this->em->flush();

                return;
            } catch (RetryableException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }

                usleep(50000);
            }
        }
    }

    /** @return array<string,mixed> */
    private function serializeDevice(UserDevice $device): array
    {
        return [
            'id' => $device->getId(),
            'deviceIdentifier' => $device->getDeviceIdentifier(),
            'deviceName' => $device->getDeviceName(),
            'deviceType' => $device->getDeviceType(),
            'status' => $device->getStatus(),
            'ipAddress' => $device->getIpAddress(),
            'requestedAt' => $device->getRequestedAt()?->format(DATE_ATOM),
            'validatedAt' => $device->getValidatedAt()?->format(DATE_ATOM),
            'lastSeenAt' => $device->getLastSeenAt()?->format(DATE_ATOM),
            'validatedBy' => $device->getValidatedBy()?->getUsername(),
            'rejectionReason' => $device->getRejectionReason(),
        ];
    }
}
