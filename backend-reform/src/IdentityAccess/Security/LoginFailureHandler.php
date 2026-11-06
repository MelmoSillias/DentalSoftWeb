<?php

namespace App\IdentityAccess\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        $throttled = $exception instanceof TooManyLoginAttemptsAuthenticationException;

        return new JsonResponse([
            'code' => $throttled ? 429 : 401,
            'message' => $throttled
                ? 'Trop de tentatives de connexion. Veuillez réessayer dans quelques minutes.'
                : 'Identifiants incorrects.',
        ], $throttled ? 429 : 401, ['Cache-Control' => 'no-store']);
    }
}
