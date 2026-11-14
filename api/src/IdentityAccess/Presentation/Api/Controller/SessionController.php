<?php

declare(strict_types=1);

namespace App\IdentityAccess\Presentation\Api\Controller;

use App\IdentityAccess\Application\Service\RefreshTokenService;
use App\IdentityAccess\Application\Service\UserDeviceService;
use App\IdentityAccess\Domain\Enum\AuthRealm;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class SessionController
{
    public function __construct(
        private RefreshTokenService $tokens,
        private UserDeviceService $devices,
        #[Autowire(service: 'limiter.auth_refresh')] private RateLimiterFactory $limiter,
    ) {}

    #[Route('/api/auth/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    #[Route('/api/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    #[Route('/api/patient-portal/auth/refresh', name: 'api_patient_auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse { return $this->handle($request, false); }

    #[Route('/api/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    #[Route('/api/patient-portal/auth/logout', name: 'api_patient_auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse { return $this->handle($request, true); }

    private function handle(Request $request, bool $logout): JsonResponse
    {
        $headers = ['Cache-Control' => 'private, no-store'];
        $limit = $this->limiter->create(hash('sha256', (string) $request->getClientIp()))->consume();
        if (!$limit->isAccepted()) return new JsonResponse(['message' => 'Trop de tentatives.'], 429, $headers + ['Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time())]);
        try { $data = $request->toArray(); }
        catch (\Symfony\Component\HttpFoundation\Exception\JsonException) { return new JsonResponse(['message' => 'Corps JSON invalide.'], 400, $headers); }
        $plain = $data['refresh_token'] ?? null;
        if (!is_string($plain) || $plain === '' || strlen($plain) > 128) return new JsonResponse(['message' => 'refresh_token requis.'], 400, $headers);
        $realm = str_starts_with($request->getPathInfo(), '/api/patient-portal/') ? AuthRealm::Patient : AuthRealm::App;
        $device = $this->devices->resolveRequestDevice($request)['id'];
        try {
            if ($logout) {
                $this->tokens->revoke($plain, $realm, $device);
                return new JsonResponse(null, 204, $headers);
            }
            return new JsonResponse($this->tokens->refresh($plain, $realm, $device), 200, $headers);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['message' => 'Jeton de renouvellement invalide.'], 401, $headers);
        }
    }
}
