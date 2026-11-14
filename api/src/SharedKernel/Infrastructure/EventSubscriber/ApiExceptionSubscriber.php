<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array { return [KernelEvents::EXCEPTION => ['onException', -64]]; }
    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->has('_legacy_route') && !preg_match('#^/api/(auth/|patient-portal/auth/|token/refresh$)#', $request->getPathInfo())) return;
        $error = $event->getThrowable();
        $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 500;
        $message = $status >= 500 ? 'Erreur interne du serveur.' : $error->getMessage();
        $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];
        $event->setResponse(new JsonResponse([
            'type' => 'about:blank', 'title' => Response::$statusTexts[$status] ?? 'Error',
            'status' => $status, 'detail' => $message, 'message' => $message,
        ], $status, $headers + ['Content-Type' => 'application/problem+json', 'Cache-Control' => 'private, no-store']));
    }
}
