<?php

namespace App\IdentityAccess\EventSubscriber;

use App\IdentityAccess\Entity\User;
use App\IdentityAccess\Service\UserDeviceService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiDeviceGuardSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private UserDeviceService $userDeviceService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', -10],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->isMethod('OPTIONS')) {
            return;
        }

        $path = (string) $request->getPathInfo();
        if (!str_starts_with($path, '/api')) {
            return;
        }

        if (str_starts_with($path, '/api/login') || str_starts_with($path, '/api/token/validate')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $result = $this->userDeviceService->enforceDeviceForRequest($user, $request);
        if ($result['allowed']) {
            return;
        }

        $event->setController(static fn() => new JsonResponse([
            'error' => 'device_not_allowed',
            'message' => $result['message'],
        ], $result['code']));
    }
}
