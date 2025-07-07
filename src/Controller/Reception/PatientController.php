<?php

namespace App\Controller\Reception;

use App\Entity\Consultation;
use App\Entity\Patient;
use App\Entity\Rdv;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Salle; // Replace with actual entities used
use App\Repository\SalleRepository; // Replace with actual repositories used
use App\Form\PatientType; // Replace with actual forms used

final class PatientController extends AbstractController
{
#[Route('/reception/patients', name: 'app_reception_patient')]
    public function patient(SalleRepository $salleRepository): Response
    {
        $salles = $salleRepository->findAll();

        return $this->render('reception/patient.html.twig', [
            'controller_name' => 'PatientController',
            'active_page' => 'patients',
            'salles' => $salles // Ajout des salles
        ]);
    }

   
    
}
