<?php

namespace App\Controller;

use App\Entity\Consultation;
use App\Entity\ContactUrgence;
use App\Entity\Employe;
use App\Entity\ModeDePaiement;
use App\Entity\PaiementDevis;
use App\Entity\Patient;
use App\Entity\Rdv;
use App\Entity\Transaction;
use App\Repository\PatientRepository;
use App\Repository\ConsultationRepository;
use App\Repository\EmployeRepository;
use App\Repository\SalleRepository;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Util\Json;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PatientAPIController extends AbstractController
{   
    #[Route('/api/patients', name: 'api_patients', methods: ['GET'])]
    public function getPatients(PatientRepository $patientRepo): JsonResponse
    {
        $patients = $patientRepo->findAll();

        $data = array_map(function ($patient) {
            $contact = $patient->getContactsUrgence()->first();
            $consultation = $patient->getDerniereConsultation();

            return [
                'id' => $patient->getId(),
                'nom' => $patient->getNom(),
                'prenom' => $patient->getPrenom(),
                'age' => $patient->getAge(), // méthode personnalisée
                'sexe' => $patient->getSexe(),
                'telephone' => $patient->getTelephone(),
                'adresse' => $patient->getAdresse(),
                'groupeSanguin' => $patient->getGroupeSanguin(),
                'contactUrgence' => $contact ? [
                    'nom' => $contact->getNom(),
                    'telephone' => $contact->getTelephone(),
                    'lienParente' => $contact->getLienParente()
                ] : null,
                'derniereConsultation' => $consultation ? [
                    'id' => $consultation->getId(),
                    'date' => $consultation->getDateDebut()?->format('Y-m-d H:i'),
                    'motif' => $consultation->getMotifConsultation()
                ] : null
            ];
        }, $patients);

        return $this->json($data);
    }

    #[Route('/api/patients/medecin', name: 'api_patients_by_medecin', methods: ['GET'])]
public function getPatientsByMedecin(
    PatientRepository $patientRepo,
    EntityManagerInterface $em
): JsonResponse {
    $user = $this->getUser();

    if (!$user) {
        return $this->json(['error' => 'Utilisateur non authentifié'], 401);
    }

    $employe = $em->getRepository(Employe::class)->findOneBy(['user' => $user]);

    if (!$employe) {
        return $this->json(['error' => 'Aucun employé associé'], 404);
    }

    // 1. Patients ayant eu une consultation avec ce médecin
    $consultations = $em->getRepository(Consultation::class)->findBy(['medecin' => $employe]);
    $patientsFromConsultations = array_map(fn($c) => $c->getPatient(), $consultations);

    // 2. Patients ayant un rendez-vous avec ce médecin
    $rdvs = $em->getRepository(Rdv::class)->findBy(['medecin' => $employe]);
    $patientsFromRdvs = array_map(fn($r) => $r->getPatient(), $rdvs);

    // 3. Fusionner les deux listes de patients et éliminer les doublons
    $patients = array_unique(array_merge($patientsFromConsultations, $patientsFromRdvs), SORT_REGULAR);

    // 4. Format JSON
    $data = array_map(function ($patient) {
        $contact = $patient->getContactsUrgence()->first();
        $consultation = $patient->getDerniereConsultation();

        return [
            'id' => $patient->getId(),
            'nom' => $patient->getNom(),
            'prenom' => $patient->getPrenom(),
            'age' => $patient->getAge(),
            'sexe' => $patient->getSexe(),
            'telephone' => $patient->getTelephone(),
            'adresse' => $patient->getAdresse(),
            'groupeSanguin' => $patient->getGroupeSanguin(),
            'contactUrgence' => $contact ? [
                'nom' => $contact->getNom(),
                'telephone' => $contact->getTelephone(),
                'lienParente' => $contact->getLienParente()
            ] : null,
            'derniereConsultation' => $consultation ? [
                'id' => $consultation->getId(),
                'date' => $consultation->getDateDebut()?->format('Y-m-d H:i'),
                'motif' => $consultation->getMotifConsultation()
            ] : null
        ];
    }, $patients);

    return $this->json(array_values($data));
}



    #[Route('/api/patient/add', name: 'api_patient_add', methods: ['POST'])]
    public function addPatient(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        error_log(print_r($data, true));

        if (!isset($data['nom'], $data['prenom'], $data['sexe'], $data['telephone'])) {
            return new JsonResponse(['message' => 'Paramètres obligatoires manquants'], 400);
        }

        try {
            $patient = new Patient();
            $patient->setNom($data['nom']);
            $patient->setPrenom($data['prenom']);
            $patient->setSexe($data['sexe']);
            $patient->setTelephone($data['telephone']);
            $patient->setAdresse($data['adresse'] ?? null);
            $patient->setDateNaissance(!empty($data['dateNaissance']) ? new \DateTime($data['dateNaissance']) : null);
            $patient->setDateInscription(new \DateTime());
            $patient->setNumCarnet(uniqid('PAT-', true));
            $patient->setGroupeSanguin($data['groupeSanguin'] ?? null);

            // === Ajout Contact d'Urgence si présent ===
            if (isset($data['contactUrgence']) && is_array($data['contactUrgence'])) {
                $contactData = $data['contactUrgence'];

                if (!empty($contactData['nom']) || !empty($contactData['telephone'])) {
                    $contact = new ContactUrgence();
                    $contact->setNom($contactData['nom'] ?? null);
                    $contact->setTelephone($contactData['telephone'] ?? null);
                    $contact->setLienParente($contactData['lienParente'] ?? null);
                    $contact->setPatient($patient); // Relation inversée

                    $em->persist($contact);
                }
            }

            $em->persist($patient);
            $em->flush();

            return new JsonResponse(['message' => 'Patient ajouté avec succès'], 201);

        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Erreur : ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/patient/{id}/update', name: 'api_patient_update', methods: ['POST'])]
    public function updatePatient(int $id,  Request $request, EntityManagerInterface $em): JsonResponse
        {
            try{
                $data = json_decode($request->getContent(), true);
            $patient = $em->getRepository(Patient::class)->find($id);

            if (!$patient) {
                return new JsonResponse(['message' => 'Patient non trouvé'], 404);
            }

            $patient->setNom($data['nom'] ?? $patient->getNom());
            $patient->setPrenom($data['prenom'] ?? $patient->getPrenom());
            $patient->setTelephone($data['telephone'] ?? $patient->getTelephone());
            $patient->setAdresse($data['adresse'] ?? $patient->getAdresse());
            $patient->setGroupeSanguin($data['groupeSanguin'] ?? $patient->getGroupeSanguin());

            // === Gestion du contact d'urgence ===
            if (isset($data['contactUrgence']) && is_array($data['contactUrgence'])) {
                $urgenceData = $data['contactUrgence'];

                // Récupération du contact existant (s’il existe)
                $existingContact = $patient->getContactsUrgence()->first();

                if ($existingContact) {
                    $existingContact->setNom($urgenceData['nom'] ?? null);
                    $existingContact->setTelephone($urgenceData['telephone'] ?? null);
                    $existingContact->setLienParente($urgenceData['lienParente'] ?? null);
                } elseif (!empty($urgenceData['nom']) || !empty($urgenceData['telephone'])) {
                    $contact = new \App\Entity\ContactUrgence();
                    $contact->setNom($urgenceData['nom'] ?? null);
                    $contact->setTelephone($urgenceData['telephone'] ?? null);
                    $contact->setLienParente($urgenceData['lienParente'] ?? null);
                    $contact->setPatient($patient);
                    $em->persist($contact);
                }
            }

            $em->flush();
            return new JsonResponse(['message' => 'Patient mis à jour avec succès'], 200);

            }
            catch (\Exception $e) {
                return new JsonResponse(['message' => 'Erreur : ' . $e->getMessage()], 500);
            }
            
        }

        #[Route('/api/patient/{id}/consultation-en-cours', name: 'api_consultation_check_active', methods: ['GET'])]
        public function checkConsultationEnCours(int $id, ConsultationRepository $consultationRepo): JsonResponse
        {
            $consultation = $consultationRepo->findOneBy([
                'patient' => $id,
                'statut' => 0 // ou selon ta logique : null/0
            ]);

            return $this->json([
                'hasActive' => $consultation !== null
            ]);
        }



    #[Route('/api/patient/{id}', name: 'api_patient_details', methods: ['GET'])]
public function getPatientDetails(int $id, PatientRepository $patientRepository): JsonResponse
{
    $patient = $patientRepository->find($id);

    if (!$patient) {
        return $this->json(['message' => 'Patient non trouvé'], Response::HTTP_NOT_FOUND);
    }

    // Calculate age
    $age = 'Néant';
    if ($patient->getDateNaissance()) {
        try {
            $dateNaissance = $patient->getDateNaissance();
            $aujourdhui = new \DateTime();
            $age = $dateNaissance->diff($aujourdhui)->y . ' ans';
        } catch (\Exception $e) {
            // Keep default 'Néant' value
        }
    }

    // Format emergency contacts
    $contactUrgence = [];
    foreach ($patient->getContactsUrgence() as $contact) {
        $contactUrgence = [
            'nom' => $contact->getNom(), 
            'lienParente' => $contact->getLienParente(),
            'telephone' => $contact->getTelephone()
        ];
    }

    // Format allergies
    $allergies = [];
    foreach ($patient->getAllergies() as $allergy) {
        $allergies[] = [
            'nom' => $allergy->getNom(),
            'severite' => $allergy->getSeverite(),
            'commentaire' => $allergy->getCommentaire()
        ];
    }

    // Format medical history (antecedents)
    $antecedents = [];
    foreach ($patient->getAntecedents() as $antecedent) {
        $antecedents[] = [
            'type' => $antecedent->getType(),
            'description' => $antecedent->getDescription(),
            'date' => $antecedent->getDate() ? $antecedent->getDate()->format('Y-m-d') : null
        ];
    }

    // Format last consultation if exists
    $derniereConsultation = null;
    if ($patient->getDerniereConsultation()) {
        $consultation = $patient->getDerniereConsultation();
        $derniereConsultation = [
            'date' => $consultation->getDate()->format('Y-m-d H:i'),
            'motif' => $consultation->getMotif(),
            'medecin' => $consultation->getMedecin() ? 
                $consultation->getMedecin()->getNomComplet() : null
        ];
    }

    return $this->json([
        'id' => $patient->getId(),
        'nom' => $patient->getNom(),
        'prenom' => $patient->getPrenom(),
        'dateNaissance' => $patient->getDateNaissance() ? $patient->getDateNaissance()->format('Y-m-d') : null,
        'age' => $age,
        'sexe' => $patient->getSexe(),
        'telephone' => $patient->getTelephone(),
        'adresse' => $patient->getAdresse(),
        'numCarnet' => $patient->getNumCarnet(),
        'groupeSanguin' => $patient->getGroupeSanguin(),
        'dateInscription' => $patient->getDateInscription()->format('Y-m-d H:i'),
        'contactUrgence' => $contactUrgence,
        'allergies' => $allergies,
        'antecedents' => $antecedents,
        'derniereConsultation' => $derniereConsultation
    ]);
}

    // src/Controller/PatientAPIController.php
    #[Route('/admin/patient/{id}/dossier', name: 'app_admin_patient_dossier')]
    public function AdminDossierMedical($id, PatientRepository $patientRepository): Response
    {
        // Ensure $id is an integer
        $id = (int)$id;

        $patient = $patientRepository->findWithMedicalData($id);

        if (!$patient) {
            throw $this->createNotFoundException('Patient non trouvé');
        }

        return $this->render('pages_bases/dossier_medical.html.twig', [
            'patient' => $patient, 'active_page' => 'patients'
        ]);
    }

    #[Route('/medecin/patient/{id}/dossier', name: 'app_medecin_patient_dossier')]
    public function MedecinDossierMedical($id, PatientRepository $patientRepository): Response
    {
        // Ensure $id is an integer
        $id = (int)$id;

        $patient = $patientRepository->findWithMedicalData($id);

        if (!$patient) {
            throw $this->createNotFoundException('Patient non trouvé');
        }

        return $this->render('pages_bases/dossier_medical.html.twig', [
            'patient' => $patient, 'active_page' => 'patients'
        ]);
    }

    #[Route('/api/consultation/create', name: 'api_consultation_create', methods: ['POST'])]
    public function createConsultation(
            Request $request,
            ConsultationRepository $consultationRepo,
            PatientRepository $patientRepo,
            EntityManagerInterface $em,
            EmployeRepository $empRepo
        ): JsonResponse {
            $data = json_decode($request->getContent(), true);
        
        try {
            $consultation = $consultationRepo->NewConsultation($data, $patientRepo, $empRepo);

            if (isset($data["mode_paiement_id"])) {
                
                $modePaiement = $em->getRepository(ModeDePaiement::class)->find($data["mode_paiement_id"]);

                $paiement = new PaiementDevis();
                $paiement->setDevis(null);
                $paiement->setMode($modePaiement);
                $paiement->setMontant(5000);
                $paiement->setDate(new \DateTime());
                $paiement->setConsultation($consultation);
                $em->persist($paiement);

                $transaction = new Transaction(); // Direct instantiation
                $transaction->setType('Entrée');
                $transaction->setMontant(5000);
                $transaction->setDateTransaction(new \DateTime());
                $transaction->setDescription('Ticket de consultation #' . $consultation->getId());
                $transaction->setModeDePaiement($modePaiement);
                $transaction->setPaiementDevis($paiement); // Link to the payment
                $em->persist($transaction); // Persist the transaction
            }

            $em->flush(); // Ensure all changes are saved

            return $this->json([
                'success' => true,
                'consultation_id' => $consultation->getId()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/api/patient/{id}/rdv/create', name: 'api_patient_rdv_create', methods: ['POST'])]
    public function createRdv(
        Request $request,
        PatientRepository $patientRepo, 
        EmployeRepository $medecinRepo,
        EntityManagerInterface $em
    ): JsonResponse { 

        $data = json_decode($request->getContent(), true);
    
    // Validate required fields
    if (!isset($data['patient_id']) || !isset($data['medecin_id']) || !isset($data['date']) || !isset($data['time'])) {
        return new JsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
    }

    $patient = $patientRepo->find($data['patient_id']);
    $medecin = $medecinRepo->find($data['medecin_id']);

    if (!$patient) {
        return new JsonResponse(['success' => false, 'error' => 'Patient not found'], 404);
    }

    if (!$medecin) {
        return new JsonResponse(['success' => false, 'error' => 'Medecin not found'], 404);
    }

    try {
        $rdv = new Rdv();
        $rdv->setPatient($patient)
            ->setMedecin($medecin)
            ->setDescription($data['description'] ?? '')
            ->setStatut(0)
            ->setDateCreation(new \DateTime())
            ->setDateRdv(new \DateTime($data['date'] . ' ' . $data['time']));

        $em->persist($rdv);
        $em->flush();

        return new JsonResponse(['success' => true, 'rdv_id' => $rdv->getId()], 201);
    } catch (\Exception $e) {
        return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
    }
}
