<?php

namespace App\Service;

use App\Entity\Employe;
use App\Repository\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Uid\Uuid;

class EmployeeService
{
    public function __construct(
        private EmployeRepository $employeRepo,
        private EntityManagerInterface $em,
        private ParameterBagInterface $params,
    ) {
    }

    public function getEmployeesPageContext(): array
    {
        return [];
    }

    public function listEmployeesPaginated(int $start, int $length, string $searchValue): array
    {
        $employees = $this->employeRepo->findEmployeesWithPagination($start, $length, $searchValue);
        $totalRecords = $this->employeRepo->count([]);
        $filteredRecords = $this->employeRepo->countFiltered($searchValue);

        $data = array_map(function (Employe $employee) {
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

        return [
            'data' => $data,
            'total' => $totalRecords,
            'filtered' => $filteredRecords,
        ];
    }

    public function createEmployee(array $data, array $files = []): array
    {
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
        $employe->setValeurSalaire((float)($data['valeurSalaire'] ?? 0));
        $employe->setComingDaysInWeek($data['comingDays'] ?? []);
        $employe->setIsOnDaysOff(false);
        $matricule = 'EMP-' . date('YmdHis');
        $employe->setMatricule($matricule);

        $savedFilePaths = $this->handleUpload($matricule, $files);
        $employe->setAdministrativeFiles($savedFilePaths);

        $this->em->persist($employe);
        $this->em->flush();

        return ['message' => 'Employé créé avec succès', 'id' => $employe->getId()];
    }

    public function updateEmployee(Employe $employee, array $data, array $files = []): array
    {
        $employee->setNom($data['nom']);
        $employee->setPrenom($data['prenom']);
        $employee->setMatricule($data['matricule']);
        $employee->setFonction($data['fonction']);
        $employee->setTelephone($data['telephone']);
        $employee->setEmail($data['email']);
        $employee->setDateEmbauche(new \DateTime($data['dateEmbauche']));
        $employee->setTypeSalaire($data['typeSalaire']);
        $employee->setValeurSalaire((float) $data['valeurSalaire']);
        $employee->setTypeContrat($data['typeContrat']);
        $employee->setDureeContrat($data['dureeContrat']);
        $employee->setComingDaysInWeek($data['comingDays'] ?? []);

        $uploadDirMatricule = $employee->getMatricule();
        $savedFilePaths = $employee->getAdministrativeFiles();
        $newFiles = $this->handleUpload($uploadDirMatricule, $files);
        $employee->setAdministrativeFiles(array_merge($savedFilePaths, $newFiles));

        $this->em->flush();

        return ['message' => 'Employé mis à jour avec succès'];
    }

    private function handleUpload(string $matricule, array $files): array
    {
        if (!$files) {
            return [];
        }

        $uploadDir = $this->params->get('kernel.project_dir') . '/public/uploads/employes/' . $matricule;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $savedFilePaths = [];
        foreach ($files as $file) {
            $newFilename = Uuid::v4()->toRfc4122() . '.' . $file->guessExtension();
            $file->move($uploadDir, $newFilename);
            $savedFilePaths[] = '/uploads/employes/' . $matricule . '/' . $newFilename;
        }

        return $savedFilePaths;
    }
}
