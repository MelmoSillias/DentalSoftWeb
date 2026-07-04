<?php

namespace App\IdentityAccess\Service;

use App\Communication\Service\NotificationRecipientResolver;
use App\Communication\Service\NotificationService;
use App\IdentityAccess\Entity\User;
use App\IdentityAccess\Entity\UserDevice;
use App\IdentityAccess\Entity\UserDeviceAccessLog;
use App\IdentityAccess\Repository\UserDeviceAccessLogRepository;
use App\IdentityAccess\Repository\UserDeviceRepository;
use App\Settings\Service\GlobalSettingsService;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class UserDeviceService
{
    private const HEARTBEAT_INTERVAL_SECONDS = 30;

    public function __construct(
        private UserDeviceRepository $userDeviceRepo,
        private UserDeviceAccessLogRepository $accessLogRepo,
        private EntityManagerInterface $em,
        private NotificationService $notificationService,
        private NotificationRecipientResolver $recipientResolver,
        private GlobalSettingsService $globalSettingsService,
    ) {
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

        $device = $this->userDeviceRepo->findOneByIdentifier($context['id']);
        $now = new \DateTimeImmutable();

        if (!$device) {
            $autoApproveEnabled = $this->globalSettingsService->isAutoApproveDevicesEnabled();
            $isBootstrapDevice = $this->userDeviceRepo->countApproved() === 0;
            $shouldAutoApprove = $autoApproveEnabled || $isBootstrapDevice;

            $device = (new UserDevice())
                ->setDeviceIdentifier($context['id'])
                ->setDeviceName($context['name'])
                ->setDeviceType($context['type'])
                ->setUserAgent($context['userAgent'])
                ->setIpAddress($context['ip'])
                ->setRequestedBy($user)
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

    /** @return array{devices: array<int, array<string,mixed>>, stats: array{approved:int,pending:int,rejected:int,total:int}, logs: array<int, array<string,mixed>>} */
    public function listGlobalDevices(int $logLimit = 50): array
    {
        return [
            'devices' => array_map(
                fn (UserDevice $device) => $this->serializeDevice($device),
                $this->userDeviceRepo->findAllOrdered()
            ),
            'stats' => $this->userDeviceRepo->countByStatus(),
            'logs' => $this->getRecentAccessLogs($logLimit),
        ];
    }

    public function approveDevice(int $deviceId, ?User $admin): array
    {
        $device = $this->userDeviceRepo->find($deviceId);
        if (!$device) {
            return ['error' => 'Appareil introuvable', 'status' => 404];
        }

        $device
            ->setStatus(UserDevice::STATUS_APPROVED)
            ->setValidatedAt(new \DateTimeImmutable())
            ->setValidatedBy($admin)
            ->setRejectionReason(null);

        $this->em->flush();

        return ['success' => true, 'device' => $this->serializeDevice($device)];
    }

    public function rejectDevice(int $deviceId, ?User $admin): array
    {
        $device = $this->userDeviceRepo->find($deviceId);
        if (!$device) {
            return ['error' => 'Appareil introuvable', 'status' => 404];
        }

        $device
            ->setStatus(UserDevice::STATUS_REJECTED)
            ->setValidatedAt(new \DateTimeImmutable())
            ->setValidatedBy($admin);

        $this->em->flush();

        return ['success' => true, 'device' => $this->serializeDevice($device)];
    }

    public function deleteDevice(int $deviceId): array
    {
        $device = $this->userDeviceRepo->find($deviceId);
        if (!$device) {
            return ['error' => 'Appareil introuvable', 'status' => 404];
        }

        $this->em->remove($device);
        $this->em->flush();

        return ['success' => true];
    }

    public function renameDevice(int $deviceId, string $name): array
    {
        $device = $this->userDeviceRepo->find($deviceId);
        if (!$device) {
            return ['error' => 'Appareil introuvable', 'status' => 404];
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            return ['error' => 'Nom invalide (1 a 255 caracteres)', 'status' => 400];
        }

        $device->setCustomName($name);
        $this->em->flush();

        return ['success' => true, 'device' => $this->serializeDevice($device)];
    }

    /** @return array<int, array<string,mixed>> */
    public function getRecentAccessLogs(int $limit = 50): array
    {
        $logs = $this->accessLogRepo->findLatest(max(1, min(200, $limit)));

        return array_map(static fn (UserDeviceAccessLog $log): array => [
            'id' => $log->getId(),
            'username' => $log->getUser()?->getUsername(),
            'path' => $log->getPath(),
            'status' => $log->getStatus(),
            'ipAddress' => $log->getIpAddress(),
            'userAgent' => $log->getUserAgent(),
            'createdAt' => $log->getCreatedAt()?->format(DATE_ATOM),
            'deviceId' => $log->getDevice()?->getId(),
            'deviceName' => $log->getDevice()?->getDisplayName(),
        ], $logs);
    }

    private function notifyNewDeviceRequest(User $user, UserDevice $device): void
    {
        $deviceName = $device->getDisplayName();

        $admins = $this->recipientResolver->admins();
        if ($admins !== []) {
            $this->notificationService->notifyMany(
                $admins,
                sprintf('Nouvelle demande d appareil "%s" (premiere connexion: %s).', $deviceName, $user->getUsername()),
                'warning',
                '/parametres/apparence',
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
            'customName' => $device->getCustomName(),
            'displayName' => $device->getDisplayName(),
            'deviceType' => $device->getDeviceType(),
            'status' => $device->getStatus(),
            'ipAddress' => $device->getIpAddress(),
            'requestedAt' => $device->getRequestedAt()?->format(DATE_ATOM),
            'requestedBy' => $device->getRequestedBy()?->getUsername(),
            'validatedAt' => $device->getValidatedAt()?->format(DATE_ATOM),
            'lastSeenAt' => $device->getLastSeenAt()?->format(DATE_ATOM),
            'validatedBy' => $device->getValidatedBy()?->getUsername(),
            'rejectionReason' => $device->getRejectionReason(),
        ];
    }
}
