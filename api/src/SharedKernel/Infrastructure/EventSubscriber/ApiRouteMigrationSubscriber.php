<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface;

final class ApiRouteMigrationSubscriber implements EventSubscriberInterface
{
    private array $successors = [];
    public function __construct(#[Autowire('%kernel.project_dir%')] string $projectDir, private UrlGeneratorInterface $urls)
    {
        foreach (json_decode(file_get_contents($projectDir.'/docs/route-migration.json'), true, 512, JSON_THROW_ON_ERROR) as $route) {
            if ($route['changed']) $this->successors[$route['legacyName']] = $route['canonicalName'];
        }
        $this->successors += ['api_login_check' => 'api_auth_login', 'api_patient_login_check' => 'api_patient_auth_login', 'api_token_refresh' => 'api_auth_refresh'];
    }

    public static function getSubscribedEvents(): array { return [KernelEvents::RESPONSE => 'onResponse']; }
    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $name = $this->successors[$request->attributes->get('_route', '')] ?? null;
        if (!$event->isMainRequest() || $name === null) return;
        // RFC 9745 structured date; no removal date has been scheduled.
        $event->getResponse()->headers->set('Deprecation', '@1794614400');
        try {
            $params = $request->attributes->get('_route_params', []);
            $url = $this->urls->generate($name, $params);
            $event->getResponse()->headers->set('Link', '<'.$url.'>; rel="successor-version"', false);
        } catch (ExceptionInterface) {
            // Optional historical identifiers may have no canonical equivalent.
        }
    }
}
