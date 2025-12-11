<?php

namespace App\Controller\Medecin;

use App\Service\PatientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class PatientController extends AbstractController
{
    #[Route('/medecin/patients', name: 'app_medecin_patient')]
    public function patient(PatientService $patientService): Response
    {
        $context = $patientService->getPatientsPageContext();

        return $this->render('medecin/patient.html.twig', array_merge($context, [
            'controller_name' => 'PatientController',
            'active_page' => 'patients',
        ]));
    }
}
