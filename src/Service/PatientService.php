<?php

namespace App\Service;

use App\Entity\Allergy;
use App\Entity\Antecedent;
use App\Entity\ContactUrgence;
use App\Entity\FicheObservation;
use App\Entity\Patient;
use App\Entity\Rdv;
use App\Entity\Consultation;
use App\Entity\Employe;
use App\Entity\ModeDePaiement;
use App\Entity\PaiementDevis;
use App\Entity\Transaction;
use App\Repository\ConsultationRepository;
use App\Repository\EmployeRepository;
use App\Repository\FicheObservationRepository;
use App\Repository\PatientRepository;
use App\Repository\SalleRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class PatientService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PatientRepository $patientRepo,
        private SalleRepository $salleRepo,
        private FicheObservationRepository $ficheRepo,
        private ConsultationRepository $consultationRepo,
        private EmployeRepository $employeRepo,
    ) {
    }

    private function formatPatientSummary(Patient $patient): array
    {
        $contact = $patient->getContactsUrgence()->first();
        $consultation = $patient->getDerniereConsultation();

        return [
            'id' => $patient->getId(),
            'nom' => $patient->getNom(),
            'prenom' => $patient->getPrenom(),
            'fullname' => $patient->getFullName(),
            'age' => $patient->getAge(),
            'sexe' => $patient->getSexe(),
            'telephone' => $patient->getTelephone(),
            'adresse' => $patient->getAdresse(),
            'groupeSanguin' => $patient->getGroupeSanguin(),
            'contactUrgence' => $contact ? [
                'nom' => $contact->getNom(),
                'telephone' => $contact->getTelephone(),
                'lienParente' => $contact->getLienParente(),
            ] : null,
            'derniereConsultation' => $consultation ? [
                'id' => $consultation->getId(),
                'date' => $consultation->getCreatedAt()?->format('Y-m-d H:i'),
                'motif' => $consultation->getNoteSeance(),
            ] : null,
        ];
    }

    public function listPatients(): array
    {
        $patients = $this->patientRepo->findBy([], ['nom' => 'ASC']);
        return array_map(fn (Patient $patient) => $this->formatPatientSummary($patient), $patients);
    }

    public function listPatientsByMedecin(?object $user): array
    {
        if (!$user) {
            return ['error' => 'Utilisateur non authentifié', 'status' => 401];
        }

        $employe = $this->employeRepo->findOneBy(['user' => $user]);
        if (!$employe) {
            return ['error' => 'Aucun employé associé', 'status' => 404];
        }

        $consultations = $this->consultationRepo->findBy(['medecin' => $employe]);
        $patientsFromConsultations = array_map(fn (Consultation $c) => $c->getPatient(), $consultations);

        $rdvs = $this->em->getRepository(Rdv::class)->findBy(['medecin' => $employe]);
        $patientsFromRdvs = array_map(fn ($r) => $r->getPatient(), $rdvs);

        $patients = array_unique(array_merge($patientsFromConsultations, $patientsFromRdvs), SORT_REGULAR);

        $data = array_map(fn (Patient $patient) => $this->formatPatientSummary($patient), $patients);

        return array_values($data);
    }

    public function addPatient(array $data): array
    {
        if (!isset($data['nom'], $data['prenom'], $data['sexe'], $data['telephone'])) {
            return ['error' => 'Paramètres obligatoires manquants', 'status' => 400];
        }

        try {
            $patient = new Patient();
            $patient->setNom($data['nom']);
            $patient->setPrenom($data['prenom']);
            $patient->setSexe($data['sexe']);
            $patient->setTelephone($data['telephone']);
            $patient->setAdresse($data['adresse'] ?? null);
            $patient->setDateNaissance(!empty($data['dateNaissance']) ? new DateTime($data['dateNaissance']) : null);
            $patient->setDateInscription(new DateTime());
            $patient->setNumCarnet(uniqid('PAT-', true));
            $patient->setGroupeSanguin($data['groupeSanguin'] ?? null);
            $patient->setReferencement('');

            if (isset($data['contactUrgence']) && is_array($data['contactUrgence'])) {
                $contactData = $data['contactUrgence'];
                if (!empty($contactData['nom']) || !empty($contactData['telephone'])) {
                    $contact = new ContactUrgence();
                    $contact->setNom($contactData['nom'] ?? null);
                    $contact->setTelephone($contactData['telephone'] ?? null);
                    $contact->setLienParente($contactData['lienParente'] ?? null);
                    $contact->setPatient($patient);
                    $this->em->persist($contact);
                }
            }

            $this->em->persist($patient);
            $this->em->flush();

            return ['success' => true, 'status' => 201, 'patientId' => $patient->getId()];
        } catch (\Exception $e) {
            return ['error' => 'Erreur : ' . $e->getMessage(), 'status' => 500];
        }
    }

    public function updatePatient(int $id, array $data): array
    {
        try {
            $patient = $this->patientRepo->find($id);
            if (!$patient) {
                return ['error' => 'Patient non trouvé', 'status' => 404];
            }

            $patient->setNom($data['nom'] ?? $patient->getNom());
            $patient->setPrenom($data['prenom'] ?? $patient->getPrenom());
            $patient->setTelephone($data['telephone'] ?? $patient->getTelephone());
            $patient->setAdresse($data['adresse'] ?? $patient->getAdresse());
            $patient->setGroupeSanguin($data['groupeSanguin'] ?? $patient->getGroupeSanguin());

            if (isset($data['contactUrgence']) && is_array($data['contactUrgence'])) {
                $urgenceData = $data['contactUrgence'];
                $existingContact = $patient->getContactsUrgence()->first();

                if ($existingContact) {
                    $existingContact->setNom($urgenceData['nom'] ?? null);
                    $existingContact->setTelephone($urgenceData['telephone'] ?? null);
                    $existingContact->setLienParente($urgenceData['lienParente'] ?? null);
                } elseif (!empty($urgenceData['nom']) || !empty($urgenceData['telephone'])) {
                    $contact = new ContactUrgence();
                    $contact->setNom($urgenceData['nom'] ?? null);
                    $contact->setTelephone($urgenceData['telephone'] ?? null);
                    $contact->setLienParente($urgenceData['lienParente'] ?? null);
                    $contact->setPatient($patient);
                    $this->em->persist($contact);
                }
            }

            $this->em->flush();

            return ['success' => true, 'status' => 200];
        } catch (\Exception $e) {
            return ['error' => 'Erreur : ' . $e->getMessage(), 'status' => 500];
        }
    }

    public function checkConsultationActive(int $id): bool
    {
        return (bool) $this->consultationRepo->findOneBy([
            'patient' => $id,
            'statut' => 0,
        ]);
    }

    public function searchPatients(string $term): array
    {
        $term = trim(strtolower($term));

        $patients = $this->patientRepo->createQueryBuilder('p')
            ->where('LOWER(p.nom) LIKE :term')
            ->orWhere('LOWER(p.prenom) LIKE :term')
            ->orWhere('p.telephone LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($patients as $patient) {
            $results[] = [
                'id' => $patient->getId(),
                'text' => $patient->getNom() . ' ' . $patient->getPrenom() . ' — ' . $patient->getTelephone(),
            ];
        }

        return $results;
    }

    public function getPatientDetailsData(int $id): ?array
    {
        $patient = $this->patientRepo->find($id);
        if (!$patient) {
            return null;
        }

        $age = 'Néant';
        if ($patient->getDateNaissance()) {
            try {
                $dateNaissance = $patient->getDateNaissance();
                $aujourdhui = new DateTime();
                $age = $dateNaissance->diff($aujourdhui)->y . ' ans';
            } catch (\Exception) {
                $age = 'Néant';
            }
        }

        $contactUrgence = [];
        foreach ($patient->getContactsUrgence() as $contact) {
            $contactUrgence = [
                'nom' => $contact->getNom(),
                'lienParente' => $contact->getLienParente(),
                'telephone' => $contact->getTelephone(),
            ];
        }

        $derniereConsultation = null;
        if ($patient->getDerniereConsultation()) {
            $consultation = $patient->getDerniereConsultation();
            $derniereConsultation = [
                'date' => $consultation->getDate()->format('Y-m-d H:i'),
                'motif' => $consultation->getMotif(),
                'medecin' => $consultation->getMedecin()?->getNomComplet(),
            ];
        }

        return [
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
            'derniereConsultation' => $derniereConsultation,
        ];
    }

    public function getPatientWithMedicalData(int $id): ?Patient
    {
        return $this->patientRepo->findWithMedicalData($id);
    }

    public function createConsultation(array $data): array
    {
        try {
            if (($data['payant'] ?? 0) == 1) {
                if (!isset($data['mode_paiement_id'])) {
                    return [
                        'error' => 'Le mode de paiement est requis pour une consultation payante.',
                        'status' => 400,
                    ];
                }

                $consultation = $this->consultationRepo->NewConsultation($data, $this->patientRepo, $this->employeRepo);
                $modePaiement = $this->em->getRepository(ModeDePaiement::class)->find($data['mode_paiement_id']);

                if (!$modePaiement) {
                    return [
                        'error' => 'Mode de paiement invalide.',
                        'status' => 400,
                    ];
                }

                $paiement = new PaiementDevis();
                $paiement->setDevis(null);
                $paiement->setMode($modePaiement);
                $paiement->setMontant(5000);
                $paiement->setDate(new DateTime());
                $paiement->setConsultation($consultation);
                $this->em->persist($paiement);

                $transaction = new Transaction();
                $transaction->setType('Entrée');
                $transaction->setMontant(5000);
                $transaction->setDateTransaction(new DateTime());
                $transaction->setDescription('Ticket de consultation #' . $consultation->getId());
                $transaction->setModeDePaiement($modePaiement);
                $transaction->setPaiementDevis($paiement);
                $this->em->persist($transaction);

                $this->em->flush();

                return [
                    'success' => true,
                    'status' => 200,
                    'consultation_id' => $consultation->getId(),
                    'paiement_id' => $paiement->getId(),
                ];
            }

            $consultation = $this->consultationRepo->NewConsultation($data, $this->patientRepo, $this->employeRepo);
            return [
                'success' => true,
                'status' => 200,
                'consultation_id' => $consultation->getId(),
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'status' => 400,
            ];
        }
    }

    public function createRdv(array $data): array
    {
        if (!isset($data['patient_id'], $data['medecin_id'], $data['date'], $data['time'])) {
            return ['error' => 'Missing required fields', 'status' => 400];
        }

        $patient = $this->patientRepo->find($data['patient_id']);
        $medecin = $this->employeRepo->find($data['medecin_id']);

        if (!$patient) {
            return ['error' => 'Patient not found', 'status' => 404];
        }

        if (!$medecin) {
            return ['error' => 'Medecin not found', 'status' => 404];
        }

        try {
            $rdv = new Rdv();
            $rdv->setPatient($patient)
                ->setMedecin($medecin)
                ->setDescription($data['description'] ?? '')
                ->setStatut(0)
                ->setDuration($data['duration'] ?? 30)
                ->setDateCreation(new DateTime())
                ->setDateRdv(new DateTime($data['date'] . ' ' . $data['time']));

            $this->em->persist($rdv);
            $this->em->flush();

            return ['success' => true, 'status' => 201, 'rdv_id' => $rdv->getId()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'status' => 500];
        }
    }

    public function getPatientsPageContext(): array
    {
        return [
            'salles' => $this->salleRepo->findAll(),
        ];
    }

    public function getDossierData(int $id): ?array
    {
        $patient = $this->patientRepo->find($id);
        if (!$patient) {
            return null;
        }

        $age = 'Néant';
        if ($patient->getDateNaissance()) {
            try {
                $age = $patient->getDateNaissance()->diff(new DateTime())->y . ' ans';
            } catch (\Exception) {
                $age = 'Néant';
            }
        }

        $contactUrgence = [];
        foreach ($patient->getContactsUrgence() as $contact) {
            $contactUrgence = [
                'nom' => $contact->getNom(),
                'lienParente' => $contact->getLienParente(),
                'telephone' => $contact->getTelephone(),
            ];
        }

        $allergies = [];
        foreach ($patient->getAllergies() as $allergy) {
            $allergies[] = [
                'libelle' => $allergy->getLibelle(),
                'description' => $allergy->getDescription(),
            ];
        }

        $antecedents = [];
        foreach ($patient->getAntecedents() as $antecedent) {
            $antecedents[] = [
                'type' => $antecedent->getType(),
                'description' => $antecedent->getDescription(),
                'date' => $antecedent->getDateEnregistrement()?->format('Y-m-d'),
            ];
        }

        $derniereConsultation = null;
        if ($patient->getDerniereConsultation()) {
            $consultation = $patient->getDerniereConsultation();
            $derniereConsultation = [
                'date' => $consultation->getDate()->format('Y-m-d H:i'),
                'motif' => $consultation->getMotif(),
                'medecin' => $consultation->getMedecin()?->getNomComplet(),
            ];
        }

        $rdvRepo = $this->em->getRepository(Rdv::class);
        $rdvs = [];
        foreach ($rdvRepo->findBy(['patient' => $patient]) as $r) {
            $rdvs[] = [
                'id' => $r->getId(),
                'dateCreation' => $r->getDateCreation(),
                'dateRdv' => $r->getDateRdv()->format('Y-m-d H:i'),
                'salle' => $r->getSalle()?->getNom(),
                'medecinNom' => $r->getMedecin()->getFullName(),
                'statut' => $r->getStatut(),
            ];
        }

        $fiches = [];
        foreach ($this->ficheRepo->findBy(['patient' => $patient]) as $f) {
            $ficheData = [
                'id' => $f->getId(),
                'motif' => $f->getMotif(),
                'histoireMaladie' => $f->getHistoireMaladie(),
                'soinsAnterieurs' => $f->getSoinsAnterieurs(),
                'exoInspection' => $f->getExoInspection(),
                'exoPalpation' => $f->getExoPalpation(),
                'endoInspection' => $f->getEndoInspection(),
                'endoPalpation' => $f->getEndoPalpation(),
                'occlusion' => $f->getOcclusion(),
                'examenParodontal' => $f->getExamenParodontal(),
                'diagnostic' => $f->getDiagnostic(),
                'traitementUrgence' => $f->getTraitementUrgence(),
                'traitementDentaire' => $f->getTraitementDentaire(),
                'traitementParodontal' => $f->getTraitementParodontal(),
                'traitementOrthodontique' => $f->getTraitementOrthodontique(),
                'autres' => $f->getAutres(),
            ];

            $examens = $f->getToothsCheck();

            $documents = [];
            foreach ($f->getDocumentsMedicaux() as $d) {
                $documents[] = [
                    'libelle' => $d->getLibelle(),
                    'dateDossier' => $d->getDateDossier()->format('Y-m-d'),
                    'description' => $d->getDescription(),
                    'url' => $d->getFichier(),
                ];
            }

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

            $precedentes = [];
            foreach ($f->getConsultations() as $s) {
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
                    'medecin' => $s->getMedecin()?->getFullName(),
                    'infirmier' => $s->getInfirmier()?->getFullName(),
                    'salle' => $s->getSalle()?->getNom(),
                    'noteSeance' => $s->getNoteSeance(),
                    'actes' => $actes,
                ];
            }

            $fiches[] = array_merge($ficheData, [
                'examens' => $examens,
                'documents' => $documents,
                'devis' => $devisData,
                'consultations' => $precedentes,
            ]);
        }

        return [
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
            'fiches' => $fiches,
        ];
    }

    public function updateDossier(int $id, array $payload): array
    {
        if (!$payload) {
            return ['error' => 'JSON invalide', 'status' => 400];
        }

        $patient = $this->patientRepo->find($id);
        if (!$patient) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        }

        $simples = ['nom', 'prenom', 'sexe', 'telephone', 'adresse', 'numCarnet', 'groupeSanguin'];
        foreach ($simples as $field) {
            if (array_key_exists($field, $payload)) {
                $setter = 'set' . ucfirst($field);
                if (method_exists($patient, $setter)) {
                    $patient->$setter($payload[$field]);
                }
            }
        }

        if (!empty($payload['dateNaissance'])) {
            $patient->setDateNaissance(new DateTime($payload['dateNaissance']));
        }

        $patient->getAllergies()->clear();
        if (isset($payload['allergies']) && is_array($payload['allergies'])) {
            foreach ($payload['allergies'] as $a) {
                $allergy = !empty($a['id']) ? $this->em->getRepository(Allergy::class)->find($a['id']) ?? new Allergy() : new Allergy();
                $allergy
                    ->setLibelle($a['libelle'] ?? null)
                    ->setDescription($a['description'] ?? null);
                $this->em->persist($allergy);
                $patient->addAllergy($allergy);
            }
        }

        $patient->getAntecedents()->clear();
        if (isset($payload['antecedents']) && is_array($payload['antecedents'])) {
            foreach ($payload['antecedents'] as $ant) {
                $ante = !empty($ant['id']) ? $this->em->getRepository(Antecedent::class)->find($ant['id']) ?? new Antecedent() : new Antecedent();
                $ante
                    ->setType($ant['type'] ?? null)
                    ->setDescription($ant['description'] ?? null)
                    ->setDateEnregistrement(new DateTimeImmutable())
                    ->setPatient($patient);
                $this->em->persist($ante);
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
                $contact = new ContactUrgence();
                $contact->setNom($c['nom'] ?? null);
                $contact->setTelephone($c['telephone'] ?? null);
                $contact->setLienParente($c['lienParente'] ?? null);
                $contact->setPatient($patient);
                $this->em->persist($contact);
            }
        }

        $this->em->persist($patient);
        $this->em->flush();

        return ['success' => true];
    }

    public function getPrintInfosPersoContext(int $id): ?Patient
    {
        return $this->patientRepo->find($id);
    }

    public function getPrintFicheContext(int $patientId, int $ficheId): ?array
    {
        $patient = $this->patientRepo->find($patientId);
        $fiche = $this->ficheRepo->find($ficheId);

        if (!$patient || !$fiche || $fiche->getPatient()->getId() !== $patientId) {
            return null;
        }

        return [
            'patient' => $patient,
            'fiche' => $fiche,
        ];
    }
}
