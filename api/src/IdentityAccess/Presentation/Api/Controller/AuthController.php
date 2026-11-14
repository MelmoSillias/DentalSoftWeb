<?php
namespace App\IdentityAccess\Presentation\Api\Controller;

use App\IdentityAccess\Domain\Entity\User;
use App\IdentityAccess\Application\Service\AuthService;
use App\IdentityAccess\Application\Service\UserDeviceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AuthController extends AbstractController
{
    public function __construct(
        private AuthService $authService,
        private UserDeviceService $userDeviceService,
    ) {
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
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
    public function updateMe(Request $request, #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDir): JsonResponse
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

    #[Route('/api/device/status', name: 'api_device_status', methods: ['GET'])]
    public function deviceStatus(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'unauthorized'], 401);
        }

        $result = $this->userDeviceService->enforceDeviceForRequest($user, $request);

        return $this->json([
            'allowed' => $result['allowed'],
            'status' => $result['device']->getStatus(),
            'message' => $result['message'],
        ], $result['allowed'] ? 200 : 403);
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
