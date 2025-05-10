<?php

namespace App\Controller\Admin;

use App\Entity\Employe;
use App\Repository\EmployeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Serializer\SerializerInterface;


final class RHController extends AbstractController
{
    #[Route('/admin/gestion-rh', name: 'app_admin_gestion_rh')]
    public function gestionRH(): Response
    {
        return $this->render('admin/employee.html.twig', [
            'controller_name' => 'RHController', 
            'active_page' => 'gestion_rh'
        ]);
    }


    #[Route('/api/employees', name: 'api_employees', methods: ['GET'])]
    public function getAllEmployees(Request $request, EmployeRepository $employeeRepository): JsonResponse
    {
        $start = $request->query->getInt('start', 0);
        $length = $request->query->getInt('length', 10);

        // Ensure the search parameter is a scalar value
        $search = $request->query->all('search');
        $searchValue = is_array($search) && isset($search['value']) ? (string) $search['value'] : '';

        $employees = $employeeRepository->findEmployeesWithPagination($start, $length, $searchValue);
        $totalRecords = $employeeRepository->count([]);
        $filteredRecords = $employeeRepository->countFiltered($searchValue);

        $data = array_map(function ($employee) {
            return [
                'id' => $employee->getId(),
                'nom' => $employee->getNom(),
                'prenom' => $employee->getPrenom(),
                'fonction' => $employee->getFonction(),
                'type' => $employee->getType(),
                'telephone' => $employee->getTelephone(),
                'dateEmbauche' => $employee->getDateEmbauche()->format('Y-m-d'),
                'email' => $employee->getEmail(),
                'matricule' => $employee->getMatricule(),
                'typeContrat' => $employee->getTypeContrat(),
                'dureeContrat' => $employee->getDureeContrat(),
                'administrativeFiles' => $employee->getAdministrativeFiles(),
            ];
        }, $employees);

        return new JsonResponse([
            'draw' => $request->query->getInt('draw', 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }


    #[Route('/api/employee/new', name: 'api_employee_creation', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = $request->request->all();
            $files = $request->files->all()['administrativeFiles'] ?? [];

            // Log data reçues
            file_put_contents('create.log', "DATA:\n" . print_r($data, true), FILE_APPEND);
            file_put_contents('create.log', "FILES:\n" . print_r($files, true), FILE_APPEND);

            $employe = new Employe();
            $employe->setNom($data['nom']);
            $employe->setPrenom($data['prenom']);
            $employe->setTelephone($data['telephone'] ?? null);
            $employe->setFonction($data['fonction']);
            $employe->setEmail($data['email']);
            $employe->setType($data['type']);
            $employe->setDateEmbauche(new \DateTime($data['dateEmbauche']));
            $employe->setTypeContrat($data['typeContrat']);
            $employe->setDureeContrat($data['dureeContrat'] ?: null);
            $employe->setTypeSalaire($data['typeSalaire']);
            $employe->setValeurSalaire((float)$data['valeurSalaire']);
            $employe->setComingDaysInWeek($data['comingDays'] ?? []);
            $employe->setIsOnDaysOff(false);
            $matricule = 'EMP-' . date('YmdHis');
            $employe->setMatricule($matricule);

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/employes/' . $matricule;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $savedFilePaths = [];
            foreach ($files as $file) {
                $newFilename = \Symfony\Component\Uid\Uuid::v4()->toRfc4122() . '.' . $file->guessExtension();
                $file->move($uploadDir, $newFilename);
                $savedFilePaths[] = '/uploads/employes/' . $matricule . '/' . $newFilename;
            }
            $employe->setAdministrativeFiles($savedFilePaths);

            $em->persist($employe);
            $em->flush();

            return new JsonResponse(['message' => 'Employé créé avec succès'], 201);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors de la création de l\'employé : ' . $e->getMessage());
            file_put_contents('create.log', "ERROR:\n" . $e->getMessage() . "\n" . $e->getTraceAsString(), FILE_APPEND);
            return new JsonResponse([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
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
    public function update(Request $request, Employe $employee, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = $request->request->all();

            if (empty($data)) {
                $this->addFlash('error', 'Aucune donnée reçue pour la mise à jour.');
                return new JsonResponse(['message' => 'Aucune donnée reçue'], 400);
            }

            $employee->setNom($data['nom']);
            $employee->setPrenom($data['prenom']);
            $employee->setMatricule($data['matricule']);
            $employee->setFonction($data['fonction']);
            $employee->setType($data['type']);
            $employee->setTelephone($data['telephone']);
            $employee->setEmail($data['email']);
            $employee->setDateEmbauche(new \DateTime($data['dateEmbauche']));
            $employee->setTypeSalaire($data['typeSalaire']);
            $employee->setValeurSalaire((float) $data['valeurSalaire']);
            $employee->setTypeContrat($data['typeContrat']);
            $employee->setDureeContrat($data['dureeContrat']);
            $employee->setComingDaysInWeek($data['comingDays'] ?? []);

            // Upload de nouveaux fichiers (les anciens peuvent être gérés à part)
            $files = $request->files->all()['administrativeFiles'] ?? [];
            $savedFilePaths = $employee->getAdministrativeFiles();
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/employes/' . $employee->getMatricule();
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            foreach ($files as $file) {
                $newFilename = Uuid::v4()->toRfc4122() . '.' . $file->guessExtension();
                $file->move($uploadDir, $newFilename);
                $savedFilePaths[] = '/uploads/employes/' . $employee->getMatricule() . '/' . $newFilename;
            }
            $employee->setAdministrativeFiles($savedFilePaths);

            $em->flush();

            return new JsonResponse(['message' => 'Employé mis à jour avec succès'], 200);
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
