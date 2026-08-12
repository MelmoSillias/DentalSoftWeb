<?php

namespace App\IdentityAccess\Controller\Api;

use App\IdentityAccess\Application\Command\CreateEmployee\CreateEmployeeCommand;
use App\IdentityAccess\Application\Command\UpdateEmployee\UpdateEmployeeCommand;
use App\IdentityAccess\Application\Query\GetEmployee\GetEmployeeQuery;
use App\IdentityAccess\Application\Query\ListEmployees\ListEmployeesQuery;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class RHController extends AbstractController
{
    public function __construct(
        private QueryBus $queryBus,
        private CommandBus $commandBus,
    ) {
    }

    #[Route('/api/employees', name: 'api_employees_list', methods: ['GET'])]
    public function listEmployees(Request $request): JsonResponse
    {
        $start = $request->query->getInt('start', 0);
        $length = $request->query->getInt('length', 10);
        $search = $request->query->all('search');
        $searchValue = is_array($search) && isset($search['value']) ? (string) $search['value'] : '';

        $result = $this->queryBus->ask(new ListEmployeesQuery($start, $length, $searchValue));

        return new JsonResponse([
            'draw' => $request->query->getInt('draw', 1),
            'recordsTotal' => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data' => $result['data'],
        ]);
    }

    #[Route('/api/employees', name: 'api_employees_create', methods: ['POST'])]
    public function createEmployee(Request $request): JsonResponse
    {
        try {
            $data = $request->request->all();
            if (empty($data)) {
                $content = $request->getContent();
                if (!empty($content)) {
                    $data = json_decode($content, true) ?? [];
                }
            }
            $files = $request->files->all()['administrativeFiles'] ?? [];

            $result = $this->commandBus->dispatch(new CreateEmployeeCommand($data, $files));

            return new JsonResponse(['message' => $result['message'], 'id' => $result['id']], 201);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 400);
        } catch (\Throwable $exception) {
            return new JsonResponse(['message' => 'Une erreur inattendue est survenue.'], 500);
        }
    }

    #[Route('/api/employees/{id}', name: 'api_employees_update', methods: ['PUT', 'POST'])]
    public function updateEmployee(Request $request, int $id): JsonResponse
    {
        try {
            $data = $request->request->all();
            if (empty($data)) {
                $content = $request->getContent();
                if (!empty($content)) {
                    $data = json_decode($content, true) ?? [];
                }
            }
            $files = $request->files->all()['administrativeFiles'] ?? [];

            $result = $this->commandBus->dispatch(new UpdateEmployeeCommand($id, $data, $files));

            return new JsonResponse($result, 200);
        } catch (\InvalidArgumentException $exception) {
            $status = str_contains($exception->getMessage(), 'introuvable') ? 404 : 400;

            return new JsonResponse(['message' => $exception->getMessage()], $status);
        } catch (\Throwable $exception) {
            return new JsonResponse(['message' => 'Une erreur inattendue est survenue.'], 500);
        }
    }

    #[Route('/api/employee/{id}', name: 'api_employee_details', methods: ['GET'])]
    public function getEmployeeDetails(int $id): JsonResponse
    {
        $details = $this->queryBus->ask(new GetEmployeeQuery($id));
        if ($details === null) {
            return $this->json(['message' => 'Employé introuvable.'], 404);
        }

        return $this->json($details);
    }
}
