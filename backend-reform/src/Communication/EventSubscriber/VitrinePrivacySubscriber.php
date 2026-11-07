<?php

namespace App\Communication\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Profiler\Profiler;

final class VitrinePrivacySubscriber implements EventSubscriberInterface
{
    public function __construct(private ?Profiler $profiler = null) {}
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 64], KernelEvents::RESPONSE => ['onResponse', -512]];
    }
    private function sensitive(string $path): bool
    {
        return preg_match('#^/api/(vitrine|administration/appreciations)(/|$)#', $path) === 1;
    }
    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest() && $this->sensitive($event->getRequest()->getPathInfo())) $this->profiler?->disable();
    }
    public function onResponse(ResponseEvent $event): void
    {
        if (!$this->sensitive($event->getRequest()->getPathInfo())) return;
        $event->getResponse()->headers->set('Cache-Control', 'private, no-store');
        $event->getResponse()->headers->set('X-Content-Type-Options', 'nosniff');
    }
}
