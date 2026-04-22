<?php

namespace App\Controller\Reception;

use App\Service\PatientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class PatientController extends AbstractController
{
    #[Route('/reception/patients', name: 'app_reception_patient')]
    public function patient(PatientService $patientService): Response
    {
        $context = $patientService->getPatientsPageContext();

        return $this->render('reception/patient.html.twig', array_merge($context, [
            'controller_name' => 'PatientController',
            'active_page' => 'patients',
        ]));
    }
}
