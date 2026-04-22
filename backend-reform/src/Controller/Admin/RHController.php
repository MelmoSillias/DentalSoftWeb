<?php

namespace App\Controller\Admin;

use App\Entity\Employe;
use App\Service\EmployeeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    #[Route('admin/employee/details/{id}', name: 'employee_details', methods: ['GET'])]
    public function employeeDetails(Employe $employee): Response
    {
        return $this->render('admin/employee_details.html.twig', [
            'active_page' => 'gestion_rh',
            'employee' => $employee,
        ]);
    }

    
}
