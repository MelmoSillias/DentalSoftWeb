<?php

namespace App\Controller\Admin;

use App\Entity\Allergy;
use App\Entity\Antecedent;
use App\Entity\Consultation;
use App\Entity\ContactUrgence;
use App\Entity\FicheObservation;
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
use App\Repository\FicheObservationRepository;
use App\Repository\PatientRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Types\DateImmutableType;

final class PatientController extends AbstractController
{
#[Route('/admin/patients', name: 'app_admin_patient')]
    public function patient(SalleRepository $salleRepository): Response
    {
        $salles = $salleRepository->findAll();

        return $this->render('admin/patient.html.twig', [
            'controller_name' => 'AdminController',
            'active_page' => 'patients',
            'salles' => $salles // Ajout des salles
        ]);
    }

    #[Route('/api/patient/{id}/dossier', name: 'api_patient_dossier_get', methods: ['GET'])]
    public function getDossier(int $id, EntityManagerInterface $em): JsonResponse
    {
        $patient = $em->getRepository(Patient::class)->find($id);
        if (!$patient) {
            return $this->json(['error' => 'Patient introuvable'], 404);
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
                'libelle' => $allergy->getLibelle(),
                'description' => $allergy->getDescription()
            ];
        }

        // Format medical history (antecedents)
        $antecedents = [];
        foreach ($patient->getAntecedents() as $antecedent) {
            $antecedents[] = [
                'type' => $antecedent->getType(),
                'description' => $antecedent->getDescription(),
                'date' => $antecedent->getDateEnregistrement() ? $antecedent->getDateEnregistrement()->format('Y-m-d') : null
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
        // RDV
        $rdvRepo = $em->getRepository(Rdv::class);
        $rdvs = [];
        foreach ($rdvRepo->findBy(['patient' => $patient]) as $r) {
            $rdvs[] = [
                'id'        => $r->getId(),
                'dateCreation' => $r->getDateCreation(), 
                'dateRdv' => $r->getDateRdv()->format('Y-m-d H:i'),
                'salle'     => $r->getSalle()?->getNom(),
                'medecinNom'=> $r->getMedecin()->getFullName(), 
                'statut'    => $r->getStatut(),
            ];
        }

        $ficheRepo = $em->getRepository(FicheObservation::class);
        $fiches = [];
        foreach($ficheRepo->findBy(['patient' => $patient]) as $f){
            $ficheData = [
                'id'                 => $f->getId(),
                'motif'              => $f->getMotif(),
                'histoireMaladie'    => $f->getHistoireMaladie(),
                'soinsAnterieurs'    => $f->getSoinsAnterieurs(),
                'exoInspection'      => $f->getExoInspection(),
                'exoPalpation'       => $f->getExoPalpation(),
                'endoInspection'     => $f->getEndoInspection(),
                'endoPalpation'      => $f->getEndoPalpation(),
                'occlusion'          => $f->getOcclusion(),
                'examenParodontal'   => $f->getExamenParodontal(),
                'diagnostic'         => $f->getDiagnostic(),
                'traitementUrgence'  => $f->getTraitementUrgence(),
                'traitementDentaire' => $f->getTraitementDentaire(),
                'traitementParodontal'=> $f->getTraitementParodontal(),
                'traitementOrthodontique'=> $f->getTraitementOrthodontique(),
                'autres'             => $f->getAutres(),
            ];

            // Examens dentaires
            $examens = $f->getToothsCheck();

            // Documents médicaux
            $documents = [];
            foreach ($f->getDocumentsMedicaux() as $d) {
                $documents[] = [
                    'libelle'    => $d->getLibelle(),
                    'dateDossier'=> $d->getDateDossier()->format('Y-m-d'),
                    'description'=> $d->getDescription(),
                    'url'        => $d->getFichier(),
                ];
            }

            // Devis
            $devis = $f->getDevis()[0] ?? null;
            $devisData = null; 
            if ($devis) {
                $contenus = [];
                foreach ($devis->getContenus() as $c) {
                    $contenus[] = [
                        'designation' => $c->getDesignation(),
                        'qte' => $c->getQte(),
                        'montant' => $c->getMontant(),
                    ];
                }
                $devisData = [
                    'date' => $devis->getDate()->format('Y-m-d'),
                    'contenus' => $contenus,
                ];
            }

            // Séances passées
            $precedentes = []; 
            foreach ($f->getConsultations() as $s) {
                // Collecte des actes pour chaque séance
                $actes = [];
                foreach ($s->getActes() as $a) {
                    $actes[] = [
                        'dent' => $a->getDent(),
                        'type' => $a->getType(),
                        'description' => $a->getDescription(),
                        'prix' => $a->getPrix(),
                        'quantite' => $a->getQuantite(),
                    ];
                }

                $precedentes[] = [
                    'id' => $s->getId(),
                    'date' => $s->getCreatedAt()->format('Y-m-d'),
                    'medecin' => $s->getMedecin() ? $s->getMedecin()->getFullName() : null,
                    'infirmier' => $s->getInfirmier() ? $s->getInfirmier()->getFullName() : null,
                    'salle' => $s->getSalle() ? $s->getSalle()->getNom() : null,
                    'noteSeance' => $s->getNoteSeance(),
                    'actes' => $actes, // Ajoute les actes collectés à la séance actuelle
                ];
            }

            $fiches[] = array_merge($ficheData, [
                    'examens'      => $examens,
                    'documents'    => $documents,
                    'devis'        => $devisData,
                    'consultations'=> $precedentes,
            ]); 
        };

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
            'derniereConsultation' => $derniereConsultation,
            'rdvs' => $rdvs,
            'fiches' => $fiches
        ]);
    }

    /**
     * PUT /api/patient/{id}/dossier
     * Met à jour les champs simples et collections du dossier patient
     */
    #[Route('/api/patient/{id}/dossier/update', name: 'api_patient_dossier_update', methods: ['PUT'])]
    public function updateDossier(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!$payload) {
            return $this->json(['error' => 'JSON invalide'], 400);
        }

        $patient = $em->getRepository(Patient::class)->find($id);
        if (!$patient) {
            return $this->json(['error' => 'Patient introuvable'], 404);
        }

        // 1. Champs simples directement dans $payload
        $simples = ['nom', 'prenom', 'sexe', 'telephone', 'adresse', 'numCarnet', 'groupeSanguin'];
        foreach ($simples as $field) {
            if (array_key_exists($field, $payload)) {
                $setter = 'set'.ucfirst($field);
                if (method_exists($patient, $setter)) {
                    $patient->$setter($payload[$field]);
                }
            }
        }

        $patient->setDateNaissance(New DateTime($payload['dateNaissance']));

        // 2. Allergies
        if (isset($payload['allergies']) && is_array($payload['allergies'])) {
        // on vide d’abord la collection
        $patient->getAllergies()->clear();

        foreach ($payload['allergies'] as $a) {
            $allergy = !empty($a['id'])
                ? $em->getRepository(Allergy::class)->find($a['id']) ?? new Allergy()
                : new Allergy()
            ;

            $allergy
                ->setLibelle($a['libelle'] ?? null)
                ->setDescription($a['description'] ?? null)
            ;

            // **1) On persiste explicitement la nouvelle entité**
            $em->persist($allergy);

            // 2) On l’attache au patient
            $patient->addAllergy($allergy);
        }
    }

    // 3. Antécédents
   // 3. Antécédents
if (isset($payload['antecedents']) && is_array($payload['antecedents'])) {
    // on vide d’abord la collection
    $patient->getAntecedents()->clear();

    foreach ($payload['antecedents'] as $ant) {
        $ante = !empty($ant['id'])
            ? $em->getRepository(Antecedent::class)->find($ant['id']) ?? new Antecedent()
            : new Antecedent()
        ;
        $ante
            ->setType($ant['type'] ?? null)
            ->setDescription($ant['description'] ?? null)
            ->setDateEnregistrement(New DateTimeImmutable())
            // n’oubliez pas de lier au patient si votre entité est bidirectionnelle :
            ->setPatient($patient)
        ;

        // **C’est cette ligne qui manquait :**
        $em->persist($ante);

        $patient->addAntecedent($ante);
    }
}

  
    if (isset($payload['contactUrgence']) && is_array($payload['contactUrgence'])) { 
        $c = $payload['contactUrgence'];

        $existingContact = $patient->getContactsUrgence()->first();

        if ($existingContact) {
            $existingContact->setNom($c['nom'] ?? null);
            $existingContact->setTelephone($c['telephone'] ?? null);
            $existingContact->setLienParente($c['lienParente'] ?? null);
        } elseif (!empty($c['nom']) || !empty($c['telephone'])) {
            $contact = new \App\Entity\ContactUrgence();
            $contact->setNom($c['nom'] ?? null);
            $contact->setTelephone($c['telephone'] ?? null);
            $contact->setLienParente($c['lienParente'] ?? null);
            $contact->setPatient($patient);
            $em->persist($contact);
        } 
    }

    $em->persist($patient);
    $em->flush();

    return $this->json(['success' => true]);
}

#[Route('/api/patient/{id}/dossier/print/infosperso', name: 'patient_print_infos_perso', methods: ['GET'])]
    public function print(int $id, PatientRepository $repo): Response
    {
        $patient = $repo->find($id);
        if (!$patient) {
            throw $this->createNotFoundException('Patient non trouvé.');
        }

        // vous pouvez ajouter d'autres données si besoin (ex. cabinet, contacts, ...)
        return $this->render('admin/printinfosperso.html.twig', [
            'patient' => $patient,
        ]);
    }

    #[Route('/api/patient/{patientId}/fiche/{ficheId}/print', name: 'patient_fiche_print', methods: ['GET'])]
    public function printFiche(
        int $patientId,
        int $ficheId,
        PatientRepository $patients,
        FicheObservationRepository $fiches
    ): Response
    {
        $patient = $patients->find($patientId);
        $fiche   = $fiches->find($ficheId);

        if (!$patient || !$fiche || $fiche->getPatient()->getId() !== $patientId) {
            throw $this->createNotFoundException('Fiche introuvable pour ce patient.');
        }

        return $this->render('pages_bases/fiche_print.html.twig', [
            'patient' => $patient,
            'fiche'   => $fiche,
        ]);
    }
    
}
