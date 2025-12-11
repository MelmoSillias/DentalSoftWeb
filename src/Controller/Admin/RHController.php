<?php

namespace App\Controller\Admin;

use App\Entity\Employe;
use App\Service\EmployeeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


final class RHController extends AbstractController
{
    public function __construct(private EmployeeService $employeeService)
    {
    }

    #[Route('/admin/gestion-rh', name: 'app_admin_gestion_rh')]
    public function gestionRH(): Response
    {
        return $this->render('admin/employee.html.twig', [
            'controller_name' => 'RHController',
            'active_page' => 'gestion_rh'
        ]);
    }

    #[Route('/api/employees', name: 'api_employees', methods: ['GET'])]
    public function getAllEmployees(Request $request): JsonResponse
    {
        $start = $request->query->getInt('start', 0);
        $length = $request->query->getInt('length', 10);
        $search = $request->query->all('search');
        $searchValue = is_array($search) && isset($search['value']) ? (string) $search['value'] : '';

        $result = $this->employeeService->listEmployeesPaginated($start, $length, $searchValue);

        return new JsonResponse([
            'draw' => $request->query->getInt('draw', 1),
            'recordsTotal' => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data' => $result['data'],
        ]);
    }

    #[Route('/api/employee/new', name: 'api_employee_creation', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->request->all();
        $files = $request->files->all()['administrativeFiles'] ?? [];

        $result = $this->employeeService->createEmployee($data, $files);

        return new JsonResponse(['message' => $result['message'], 'id' => $result['id']], 201);
    }


    #[Route('admin/employee/details/{id}', name: 'employee_details', methods: ['GET'])]
    public function employeeDetails(Employe $employee): Response
    {
        return $this->render('admin/employee_details.html.twig', [
            'active_page' => 'gestion_rh',
            'employee' => $employee,
        ]);
    }
    

    #[Route('/api/employee/update/{id}', name: 'api_employee_update', methods: ['POST', 'GET'])]
    public function update(Request $request, Employe $employee): JsonResponse
    {
        try {
            $data = $request->request->all();

            if (empty($data)) {
                $this->addFlash('error', 'Aucune donnée reçue pour la mise à jour.');
                return new JsonResponse(['message' => 'Aucune donnée reçue'], 400);
            }

            $files = $request->files->all()['administrativeFiles'] ?? [];
            $result = $this->employeeService->updateEmployee($employee, $data, $files);

            return new JsonResponse($result, 200);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors de la mise à jour de l\'employé : ' . $e->getMessage());
            return new JsonResponse([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }
}
