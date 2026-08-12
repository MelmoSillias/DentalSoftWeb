<?php

namespace App\IdentityAccess\Controller\Api;

use App\IdentityAccess\Application\Command\CreateUser\CreateUserCommand;
use App\IdentityAccess\Application\Command\DeleteUser\DeleteUserCommand;
use App\IdentityAccess\Application\Command\ResetUserPassword\ResetUserPasswordCommand;
use App\IdentityAccess\Application\Command\UpdateUser\UpdateUserCommand;
use App\IdentityAccess\Application\Query\ListUsers\ListUsersQuery;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\IdentityAccess\Service\EmployeeService;
use App\IdentityAccess\Service\UserManagementService;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    public function __construct(
        private UserManagementService $userService,
        private EmployeeService $employeeService,
        private CommandBus $commandBus,
        private QueryBus $queryBus,
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
        $result = $this->commandBus->dispatch(new CreateUserCommand(
            $this->jsonPayload($request),
            $actor instanceof User ? $actor : null,
        ));
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/users', name: 'api_users_list', methods: ['GET'])]
    public function listUsers(Request $request): JsonResponse
    {
        $result = $this->queryBus->ask(new ListUsersQuery());

        return new JsonResponse([
            'data' => $result,
        ]);
    }

    #[Route('/api/users/associations', name: 'api_users_associations', methods: ['GET'])]
    public function listUserAssociations(): JsonResponse
    {
        $result = $this->userService->getAvailableAssociations();

        return new JsonResponse($result);
    }

    #[Route('/api/users/{id}', name: 'api_users_update', methods: ['PUT'])]
    public function updateUser(int $id, Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $payload['user_id'] = $id;

        $actor = $this->getUser();

        $result = $this->commandBus->dispatch(new UpdateUserCommand(
            $payload,
            $actor instanceof User ? $actor : null,
        ));
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/users/{id}/reset-password', name: 'api_users_reset_password', methods: ['POST'])]
    public function resetPassword(int $id, Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $payload['user_id'] = $id;

        $actor = $this->getUser();

        $result = $this->commandBus->dispatch(new ResetUserPasswordCommand(
            $payload,
            $actor instanceof User ? $actor : null,
        ));
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }

    #[Route('/api/users/{id}', name: 'api_users_delete', methods: ['DELETE'])]
    public function deleteUser(int $id, Request $request): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $payload['user_id'] = $id;

        $actor = $this->getUser();

        $result = $this->commandBus->dispatch(new DeleteUserCommand(
            $payload,
            $actor instanceof User ? $actor : null,
        ));
        $status = $result['status'] ?? (isset($result['error']) ? 400 : 200);

        return new JsonResponse($result, $status);
    }
}
