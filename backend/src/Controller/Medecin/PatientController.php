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

    #[Route('/medecin/patient/{id}/dossier', name: 'app_medecin_patient_dossier')]
    public function MedecinDossierMedical(int $id, PatientService $patientService): Response
    {
        $patient = $patientService->getPatientWithMedicalData($id);

        if (!$patient) {
            throw $this->createNotFoundException('Patient non trouvé');
        }

        return $this->render('pages_bases/dossier_medical_medecin.html.twig', [
            'patient' => $patient, 'active_page' => 'patients'
        ]);
    }
}
