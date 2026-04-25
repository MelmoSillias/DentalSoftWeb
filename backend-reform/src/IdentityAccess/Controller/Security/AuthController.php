<?php
namespace App\IdentityAccess\Controller\Security;

use App\IdentityAccess\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    public function __construct(private AuthService $authService)
    {
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->respond(fn() => $this->authService->register($data), 201);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return $this->respond(fn() => $this->authService->me());
    }

    #[Route('/api/me', name: 'api_me_update', methods: ['PUT', 'POST'])]
    public function updateMe(Request $request, string $uploadDir = __DIR__ . '/../../public/uploads'): JsonResponse
    {
        $contentType = $request->headers->get('Content-Type', '');
        $data = str_starts_with($contentType, 'application/json')
            ? (json_decode($request->getContent(), true) ?: [])
            : $request->request->all();

        $file = $request->files->get('photo');

        return $this->respond(fn() => $this->authService->updateMe($data, $file, $uploadDir));
    }

    #[Route('/api/me/change-password', name: 'api_me_change_password', methods: ['PATCH'])]
    public function changePassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        return $this->respond(fn() => $this->authService->changePassword($data));
    }

    // #[Route('/api/me/logs', name: 'api_me_logs', methods: ['GET'])]
    // public function myLogs(): JsonResponse
    // {
    //     return $this->respond(fn() => $this->authService->myLogs());
    // }

    #[Route('/api/token/validate', name: 'api_token_validate', methods: ['GET'])] 
    public function validateToken(): JsonResponse
    {
        return $this->respond(fn() => $this->authService->validateToken(), 200);
    }

    private function respond(callable $callback, int $status = 200): JsonResponse
    {
        try {
            return $this->json($callback(), $status);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable) {
            return $this->json(['error' => 'server_error'], 500);
        }
    }
}
