<?php

namespace App\IdentityAccess\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class AuthenticationResponseSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (in_array($event->getRequest()->getPathInfo(), ['/api/login_check', '/api/login_patient_check', '/api/token/validate', '/api/me'], true)) {
            $event->getResponse()->headers->set('Cache-Control', 'private, no-store');
        }
    }
}
