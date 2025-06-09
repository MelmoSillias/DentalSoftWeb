<?php

namespace App\Controller\Medecin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Consultation;
use App\Repository\ConsultationRepository;
use App\Form\ConsultationType;
use App\Repository\EmployeRepository;

final class ApiController extends AbstractController
{
#[Route('/api/consultations/closed', name: 'api_consultations_closed', methods: ['GET'])]
    public function getClosedConsultations(ConsultationRepository $consultationRepo): JsonResponse
    {
        $consultations = $consultationRepo->findClosedConsultations();

        $data = array_map(function (Consultation $consultation) {
            return [
                'id' => $consultation->getId(),
                'patient' => $consultation->getPatient()?->getFullName(),
                'medecin' => $consultation->getMedecin()?->getFullName(),  
                'date' => $consultation->getCreatedAt()?->format('Y-m-d H:i:s'),
                'salle' => $consultation->getSalle()?->getNom(),
                'state' => $consultation->getStatut(),
            ];
        }, $consultations);

        return new JsonResponse($data);
    }

    #[Route('/api/medecins', name: 'api_medecins', methods: ['GET'])]
    public function getAllMedecins(EmployeRepository $employeeRepository): JsonResponse
    {
        // Récupération de tous les employés via la méthode personnalisée du repository
        $employees = $employeeRepository->FindAllMedecin();

        // Transformation des entités en tableau
        $data = [];
        foreach ($employees as $employee) {
            $data[] = [
                'id'           => $employee->getId(),
                'nom'          => $employee->getNom(),
                'prenom'       => $employee->getPrenom(),
                'fonction'     => $employee->getFonction(),
                'type'         => $employee->getType(),
                'dateEmbauche' => $employee->getDateEmbauche()->format('Y-m-d'),
                'comingDays'   => $employee->getComingDaysInWeek()
                // Ajoutez ici d'autres champs si nécessaire
            ];
        }

        return new JsonResponse($data);
    }
}
