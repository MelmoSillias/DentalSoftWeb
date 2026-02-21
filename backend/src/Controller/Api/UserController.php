<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\UserManagementService;
use App\Service\EmployeeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    public function __construct(
        private UserManagementService $userService,
        private EmployeeService $employeeService,
    ) {
    }

    private function jsonPayload(Request $request): array
    {
        $content = $request->getContent();
        if ($content) {
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data ?? [];
            }
        }

        return $request->request->all();
    }

    #[Route('/api/users', name: 'api_users_create', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        $actor = $this->getUser();
        $result = $this->userService->createUser(
            $this->jsonPayload($request),
            $actor instanceof User ? $actor : null,
        );
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/users', name: 'api_users_list', methods: ['GET'])]
    public function listUsers(Request $request): JsonResponse
    { 
        $result = $this->userService->getUserList();  

        return new JsonResponse([
            'data' => $result,
        ]);
    }

    #[Route('/api/users/{id}', name: 'api_users_update', methods: ['PUT'])]
    public function updateUser(int $id, Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $payload['user_id'] = $id;

        $actor = $this->getUser();

        $result = $this->userService->updateUser(
            $payload,
            $actor instanceof User ? $actor : null,
        );
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/users/{id}/reset-password', name: 'api_users_reset_password', methods: ['POST'])]
    public function resetPassword(int $id, Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $payload['user_id'] = $id;

        $actor = $this->getUser();

        $result = $this->userService->resetPassword(
            $payload,
            $actor instanceof User ? $actor : null,
        );
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/users/{id}', name: 'api_users_delete', methods: ['DELETE'])]
    public function deleteUser(int $id, Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $payload['user_id'] = $id;

        $actor = $this->getUser();

        $result = $this->userService->deleteUser(
            $payload,
            $actor instanceof User ? $actor : null,
        );
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }
}