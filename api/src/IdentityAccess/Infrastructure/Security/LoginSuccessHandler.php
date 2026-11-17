<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Security;

use App\IdentityAccess\Application\Service\RefreshTokenService;
use App\IdentityAccess\Application\Service\UserDeviceService;
use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Domain\Enum\AuthRealm;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private RefreshTokenService $tokens, private UserDeviceService $devices) {}
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();
        if (!$user instanceof User || ($realm = AuthRealm::forUser($user)) === null) {
            return new JsonResponse(['message' => 'Identifiants incorrects.'], 401);
        }
        return new JsonResponse($this->tokens->createTokenPair($user, $realm, $this->devices->resolveRequestDevice($request)['id']), 200, ['Cache-Control' => 'private, no-store']);
    }
}
