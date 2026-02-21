<?php

namespace App\Controller\Admin;

use App\Service\PatientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class PatientController extends AbstractController
{
    public function __construct(private PatientService $patientService)
    {
    }

    #[Route('/admin/patients', name: 'app_admin_patient')]
    public function patient(): Response
    {
        $context = $this->patientService->getPatientsPageContext();

        return $this->render('admin/patient.html.twig', [
            'controller_name' => 'AdminController',
            'active_page' => 'patients',
            'salles' => $context['salles'],
        ]);
    }

    #[Route('/admin/patient/{id}/dossier', name: 'app_admin_patient_dossier')]
    public function AdminDossierMedical(int $id): Response
    {
        $patient = $this->patientService->getPatientWithMedicalData($id);

        if (!$patient) {
            throw $this->createNotFoundException('Patient non trouvé');
        }

        return $this->render('pages_bases/dossier_medical.html.twig', [
            'patient' => $patient, 'active_page' => 'patients'
        ]);
    }
}
