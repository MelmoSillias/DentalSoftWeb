<?php

namespace App\IdentityAccess\Controller\Api;

use App\Scheduling\Entity\Conge;
use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Entity\SalaryPayment;
use App\IdentityAccess\Service\EmployeeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class RHController extends AbstractController
{
    public function __construct(private EmployeeService $employeeService)
    {
    }

    #[Route('/api/employees', name: 'api_employees_list', methods: ['GET'])]
    public function listEmployees(Request $request): JsonResponse
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

    #[Route('/api/employees', name: 'api_employees_create', methods: ['POST'])]
    public function createEmployee(Request $request): JsonResponse
    {
        try {
            // Get data from request body (form or JSON)
            $data = $request->request->all();
            if (empty($data)) {
                $content = $request->getContent();
                if (!empty($content)) {
                    $data = json_decode($content, true) ?? [];
                }
            }
            $files = $request->files->all()['administrativeFiles'] ?? [];

            $result = $this->employeeService->createEmployee($data, $files);

            return new JsonResponse(['message' => $result['message'], 'id' => $result['id']], 201);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 400);
        } catch (\Throwable $exception) {
            return new JsonResponse(['message' => 'Une erreur inattendue est survenue.'], 500);
        }
    }

    #[Route('/api/employees/{id}', name: 'api_employees_update', methods: ['PUT', 'POST'])]
    public function updateEmployee(Request $request, Employe $employee): JsonResponse
    {
        try {
            // Get data from request body (form or JSON)
            $data = $request->request->all();
            if (empty($data)) {
                $content = $request->getContent();
                if (!empty($content)) {
                    $data = json_decode($content, true) ?? [];
                }
            }
            $files = $request->files->all()['administrativeFiles'] ?? [];
 
            $result = $this->employeeService->updateEmployee($employee, $data, $files);

            return new JsonResponse($result, 200);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 400);
        } catch (\Throwable $exception) {
            return new JsonResponse(['message' => 'Une erreur inattendue est survenue.'], 500);
        }
    }

    #[Route('/api/employee/{id}', name: 'api_employee_details', methods: ['GET'])]
    public function getEmployeeDetails(Employe $employee): JsonResponse
    {
        $revenue = $this->employeeService->computeMedecinRevenue($employee);
        $salaireCalcule = $this->employeeService->computeSalaireFromRevenue($employee, $revenue);

        $conges = array_map(
            static fn(Conge $conge) => [
                'id' => $conge->getId(),
                'type' => $conge->getType(),
                'startDate' => $conge->getStartDate()?->format('Y-m-d'),
                'endDate' => $conge->getEndDate()?->format('Y-m-d'),
            ],
            $employee->getConges()->toArray()
        );

        $salaryPayments = array_map(
            static fn(SalaryPayment $payment) => [
                'id' => $payment->getId(),
                'month' => $payment->getMonth(),
                'year' => $payment->getYear(),
                'workedDay' => $payment->getWorkedDay()?->format('Y-m-d'),
                'frequenceSnapshot' => $payment->getFrequenceSnapshot(),
                'salaryType' => $payment->getSalaryTypeSnapshot(),
                'salaryValue' => $payment->getSalaryValueSnapshot(),
                'primeType' => $payment->getPrimeTypeSnapshot(),
                'primeValue' => $payment->getPrimeValueSnapshot(),
                'baseSalaryAmount' => $payment->getBaseSalaryAmount(),
                'primeAmount' => $payment->getPrimeAmount(),
                'baseAmount' => $payment->getBaseAmount(),
                'calculatedAmount' => $payment->getCalculatedAmount(),
                'paidAmount' => $payment->getPaidAmount(),
                'paidAt' => $payment->getPaidAt()?->format('Y-m-d'),
                'note' => $payment->getNote(),
                'paymentMethod' => $payment->getModeDePaiement() ? [
                    'id' => $payment->getModeDePaiement()->getId(),
                    'libelle' => $payment->getModeDePaiement()->getLibelle(),
                ] : null,
            ],
            $employee->getSalaryPayments()->toArray()
        );

        usort(
            $salaryPayments,
            static fn(array $left, array $right) => strcmp((string) ($right['paidAt'] ?? ''), (string) ($left['paidAt'] ?? ''))
        );

        return $this->json([
            'id' => $employee->getId(),
            'nom' => $employee->getNom(),
            'prenom' => $employee->getPrenom(),
            'fullname' => $employee->getFullName(),
            'matricule' => $employee->getMatricule(),
            'fonction' => $employee->getFonction(),
            'type' => $employee->getType(),
            'telephone' => $employee->getTelephone(),
            'email' => $employee->getEmail(),
            'dateEmbauche' => $employee->getDateEmbauche()?->format('Y-m-d'),
            'typeContrat' => $employee->getTypeContrat(),
            'dureeContrat' => $employee->getDureeContrat(),
            'typeSalaire' => $employee->getTypeSalaire(),
            'valeurSalaire' => $employee->getValeurSalaire(),
            'frequencePaiement' => $employee->getFrequencePaiement(),
            'typePrime' => $employee->getTypePrime(),
            'valeurPrime' => $employee->getValeurPrime(),
            'revenuMedecin' => $revenue,
            'salaireCalcule' => $salaireCalcule,
            'comingDays' => $employee->getComingDaysInWeek(),
            'administrativeFiles' => $employee->getAdministrativeFiles(),
            'conges' => $conges,
            'salaryPayments' => $salaryPayments,
        ]);
    }
}