<?php

namespace App\Patient\Service;

use App\Billing\Entity\ModeDePaiement;
use App\Billing\Entity\PaiementDevis;
use App\Billing\Entity\Transaction;
use App\CareDelivery\Entity\ActeMedical;
use App\CareDelivery\Entity\Consultation;
use App\CareDelivery\Entity\Ordonnance;
use App\CareDelivery\Entity\OrdonnanceLigne;
use App\CareDelivery\Service\ConsultationNotificationService;
use App\ClinicalRecord\Entity\FicheMedicale;
use App\ClinicalRecord\Service\FicheMedicaleService;
use App\Communication\Service\NotificationRecipientResolver;
use App\Communication\Service\SmsService;
use App\Shared\Event\EntityActionEvent;
use App\Focus\Service\FocusRealtimePublisher;
use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Entity\User;
use App\IdentityAccess\Repository\EmployeRepository;
use App\IdentityAccess\Repository\UserRepository;
use App\Patient\Entity\Allergy;
use App\Patient\Entity\Antecedent;
use App\Patient\Entity\ContactUrgence;
use App\Patient\Entity\Patient;
use App\Patient\Repository\PatientRepository;
use App\CareDelivery\Repository\ConsultationRepository;
use App\Scheduling\Entity\Rdv;
use App\Scheduling\Repository\SalleRepository;
use App\Scheduling\Service\RdvNotificationService;
use App\Settings\Service\GlobalSettingsService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use App\Billing\Service\CashdeskService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PatientService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PatientRepository $patientRepo,
        private SalleRepository $salleRepo,
        private ConsultationRepository $consultationRepo,
        private EmployeRepository $employeRepo,
        private ConsultationNotificationService $consultationNotificationService,
        private NotificationRecipientResolver $notificationRecipientResolver,
        private RdvNotificationService $rdvNotificationService,
        private UserRepository $userRepo,
        private UserPasswordHasherInterface $passwordHasher,
        private CashdeskService $cashdeskService,
        private FicheMedicaleService $ficheMedicaleService,
        private SmsService $smsService,
        private GlobalSettingsService $globalSettingsService,
        private CacheInterface $cache,
        private EventDispatcherInterface $eventDispatcher,
        private FocusRealtimePublisher $focusRealtimePublisher,
    ) {
    }

    private function resolveLatestConsultation(Patient $patient): ?Consultation
    {
        $latest = $this->consultationRepo->findLatestByPatient($patient);

        if ($latest !== null) {
            return $latest;
        }

        return $patient->getDerniereConsultation();
    }

    private function formatPatientSummary(Patient $patient): array
    {
        $contact = $patient->getContactUrgence();
        $consultation = $this->resolveLatestConsultation($patient);

        return [
            'id' => $patient->getId(),
            'nom' => $patient->getNom(),
            'prenom' => $patient->getPrenom(),
            'fullname' => $patient->getFullName(),
            'age' => $patient->getAge(),
            'dateNaissance' => $patient->getDateNaissance() ? $patient->getDateNaissance()->format('Y-m-d') : null,
            'sexe' => $patient->getSexe(),
            'telephone' => $patient->getTelephone(),
            'dateInscription' => $patient->getDateInscription() ? $patient->getDateInscription()->format('Y-m-d H:i') : null,
            'email' => $patient->getEmail(),
            'adresse' => $patient->getAdresse(),
            'profession' => $patient->getProfession(),
            'lieuNaissance' => $patient->getLieuNaissance(),
            'groupeSanguin' => $patient->getGroupeSanguin(),
            'contactUrgence' => $contact ? [
                'nom' => $contact->getNom(),
                'telephone' => $contact->getTelephone(),
                'lienParente' => $contact->getLienParente(),
            ] : null,
            'smsPreferences' => $this->extractSmsPreferences($patient),
            'derniereConsultation' => $consultation ? [
                'id' => $consultation->getId(),
                'date' => $consultation->getCreatedAt()?->format('Y-m-d H:i'),
                'motif' => $consultation->getNoteSeance(),
                'statut' => $consultation->getStatut(),
            ] : null,
            'impayees' => $this->getPatientImpayees($patient->getId()),
        ];
    }

    public function getPatientImpayees(int $id): int
    {
        $factures = $this->cashdeskService->listDevisImpayesByPatient($id);
        $impayees = 0;
        foreach ($factures as $facture) {
            // You can add more detailed info if needed
            $impayees += $facture['reste'];
        }
        return $impayees;
    }

    public function listPatients(): array
    {
        $patients = $this->patientRepo->findBy([], ['nom' => 'ASC']);
        return array_map(fn (Patient $patient) => $this->formatPatientSummary($patient), $patients);
    }

    public function listPatientsPaginated(int $page, int $limit, ?string $query = null, ?string $sortField = null, ?string $sortOrder = null): array
    {
        $page = max(1, $page);
        $limit = max(1, min($limit, 100));
        $cacheKey = sprintf('patients.list.%d.%d.%s.%s.%s', $page, $limit, sha1((string) $query), $sortField ?? 'default', $sortOrder ?? 'asc');

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($page, $limit, $query, $sortField, $sortOrder) {
            $item->expiresAfter(60);
            $result = $this->patientRepo->paginatePatients($page, $limit, $query, $sortField, $sortOrder);
            $items = array_map(fn (Patient $patient) => $this->formatPatientSummary($patient), $result['items']);

            return [
                'items' => $items,
                'total' => $result['total'],
                'page' => $page,
                'limit' => $limit,
                'sortField' => $sortField,
                'sortOrder' => $sortOrder,
            ];
        });
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

    public function listPatientsByMedecinPaginated(?object $user, int $page, int $limit, ?string $query = null, ?string $sortField = null, ?string $sortOrder = null): array
    {
        if (!$user) {
            return ['error' => 'Utilisateur non authentifié', 'status' => 401];
        }

        $employe = $this->employeRepo->findOneBy(['user' => $user]);
        if (!$employe) {
            return ['error' => 'Aucun employé associé', 'status' => 404];
        }

        $page = max(1, $page);
        $limit = max(1, min($limit, 100));
        $cacheKey = sprintf('patients.medecin.%d.%d.%d.%s.%s.%s', $employe->getId(), $page, $limit, sha1((string) $query), $sortField ?? 'default', $sortOrder ?? 'asc');

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($employe, $page, $limit, $query, $sortField, $sortOrder) {
            $item->expiresAfter(60);
            $result = $this->patientRepo->paginatePatientsByMedecin($employe, $page, $limit, $query, $sortField, $sortOrder);
            $items = array_map(fn (Patient $patient) => $this->formatPatientSummary($patient), $result['items']);

            return [
                'items' => $items,
                'total' => $result['total'],
                'page' => $page,
                'limit' => $limit,
                'sortField' => $sortField,
                'sortOrder' => $sortOrder,
            ];
        });
    }

    public function listPatientConsultations(int $patientId): array
    {
        $consultations = $this->consultationRepo->findConsultationsByPatient($patientId);

        return array_map(function (Consultation $consultation) {
            $facture = $consultation->getFacture();

            return [
                'id' => $consultation->getId(),
                'date' => $consultation->getCreatedAt()?->format('Y-m-d H:i'),
                'statut' => $consultation->getStatut(),
                'medecin' => $consultation->getMedecin()?->getFullName(),
                'factureMontant' => $facture?->getMontant(),
                'factureStatut' => $facture?->getStatut(),
            ];
        }, $consultations);
    }

    public function addPatient(array $data, ?User $actor = null): array
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
            $patient->setEmail($data['email'] ?? null);
            $patient->setAdresse($data['adresse'] ?? null);
            $patient->setProfession($data['profession'] ?? null);
            $patient->setLieuNaissance($data['lieuNaissance'] ?? null);
            $patient->setDateNaissance(!empty($data['dateNaissance']) ? new DateTime($data['dateNaissance']) : null);
            $patient->setDateInscription(new DateTime());
            $patient->setNumCarnet(uniqid('PAT-', true));
            $patient->setGroupeSanguin($data['groupeSanguin'] ?? null);
            $patient->setReferencement('');
            $this->applySmsPreferences($patient, $data);

            if (isset($data['contactUrgence']) && is_array($data['contactUrgence'])) {
                $contactData = $data['contactUrgence'];
                $hasContact = !empty($contactData['nom']) || !empty($contactData['telephone']) || !empty($contactData['lienParente']);
                if ($hasContact) {
                    $contact = new ContactUrgence();
                    $contact->setNom($contactData['nom'] ?? null);
                    $contact->setTelephone($contactData['telephone'] ?? null);
                    $contact->setLienParente($contactData['lienParente'] ?? null);
                    $contact->setPatient($patient);
                    $patient->setContactUrgence($contact);
                    $this->em->persist($contact);
                }
            }

            $this->em->persist($patient);
            $this->em->flush();

            $this->notifyPatientCreation($patient, $actor);
            $this->focusRealtimePublisher->publishPatientRefresh($patient, 'created');
            $this->smsService->queueTemplateForPatient($patient, 'patient_created', [
                'patient_name' => trim(($patient->getPrenom() ?? '') . ' ' . ($patient->getNom() ?? '')),
                'cabinet_name' => 'ORODENT',
            ], 'patient-created');

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
            $patient->setEmail($data['email'] ?? $patient->getEmail());
            $patient->setAdresse($data['adresse'] ?? $patient->getAdresse());
            $patient->setProfession($data['profession'] ?? $patient->getProfession());
            $patient->setLieuNaissance($data['lieuNaissance'] ?? $patient->getLieuNaissance());
            $patient->setGroupeSanguin($data['groupeSanguin'] ?? $patient->getGroupeSanguin());
            $patient->setSexe($data['sexe'] ?? $patient->getSexe());
            $this->applySmsPreferences($patient, $data);
            if (!empty($data['dateNaissance'])) {
                $patient->setDateNaissance(new DateTime($data['dateNaissance']));
            }
             
            if (isset($data['contactUrgence']) && is_array($data['contactUrgence'])) {
                $urgenceData = $data['contactUrgence'];
                $hasContact = !empty($urgenceData['nom']) || !empty($urgenceData['telephone']) || !empty($urgenceData['lienParente']);
                $existingContact = $patient->getContactUrgence();

                if ($hasContact) {
                    if (!$existingContact) {
                        $existingContact = new ContactUrgence();
                        $existingContact->setPatient($patient);
                        $patient->setContactUrgence($existingContact);
                    }
                    $existingContact->setNom($urgenceData['nom'] ?? null);
                    $existingContact->setTelephone($urgenceData['telephone'] ?? null);
                    $existingContact->setLienParente($urgenceData['lienParente'] ?? null);
                    $this->em->persist($existingContact);
                } elseif ($existingContact) {
                    $patient->setContactUrgence(null);
                    $this->em->remove($existingContact);
                }
            }

            $this->em->persist($patient);
            $this->em->flush();
            $this->focusRealtimePublisher->publishPatientRefresh($patient, 'updated');

            return ['success' => true, 'status' => 200];
        } catch (\Exception $e) {
            return ['error' => 'Erreur : ' . $e->getMessage(), 'status' => 500];
        }
    }

    public function checkConsultationActive(int $id): array
    {
        $consultation = $this->consultationRepo->findOneBy([
            'patient' => $id,
            'statut' => 0,
        ]);

        if (!$consultation) {
            return [
                'hasActive' => false,
                'consultationId' => null,
                'hasFiche' => false,
            ];
        }

        return [
            'hasActive' => true,
            'consultationId' => $consultation->getId(),
            'hasFiche' => $consultation->getFiche() !== null || $consultation->getFicheMedicale() !== null,
        ];
    }

    public function searchPatients(string $term, int $limit = 20): array
{
    $term = mb_strtolower(trim($term));
    $limit = max(1, min($limit, 50));

    if ($term === '') {
        return [];
    }

    $cacheKey = sprintf('patients.search.%d.%s', $limit, sha1($term));

    return $this->cache->get($cacheKey, function (ItemInterface $item) use ($term, $limit) {

        $item->expiresAfter(60);

        $qb = $this->patientRepo->createQueryBuilder('p')
            ->select('p.id, p.nom, p.prenom, p.telephone');

        $like = '%' . $term . '%';

        $orX = $qb->expr()->orX(
            'LOWER(p.nom) LIKE :term',
            'LOWER(p.prenom) LIKE :term',
            'LOWER(p.telephone) LIKE :term',
            "LOWER(CONCAT(p.nom, ' ', p.prenom)) LIKE :term",
            "LOWER(CONCAT(p.prenom, ' ', p.nom)) LIKE :term"
        );

        // Recherche numerique telephone (sans fonctions SQL custom en DQL)
        $digits = preg_replace('/\D+/', '', $term);
        if (!empty($digits)) {
            $orX->add('p.telephone LIKE :digitsRaw');
            $qb->setParameter('digitsRaw', '%' . $digits . '%');
        }

        $qb->andWhere($orX)
           ->setParameter('term', $like);

        $rows = $qb
            ->orderBy('p.nom', 'ASC')
            ->addOrderBy('p.prenom', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(function (array $row) {
            $fullname = trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? ''));

            return [
                'id' => $row['id'],
                'nom' => $row['nom'] ?? '',
                'prenom' => $row['prenom'] ?? '',
                'fullname' => $fullname,
                'telephone' => $row['telephone'] ?? '',
            ];
        }, $rows);
    });
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

        $contact = $patient->getContactUrgence();
        $contactUrgence = $contact ? [
            'nom' => $contact->getNom(),
            'lienParente' => $contact->getLienParente(),
            'telephone' => $contact->getTelephone(),
        ] : null;

        $derniereConsultation = null;
        $latestConsultation = $this->resolveLatestConsultation($patient);
        if ($latestConsultation) {
            $consultation = $latestConsultation;
            $derniereConsultation = [
                'date' => $consultation->getCreatedAt()?->format('Y-m-d H:i'),
                'motif' => $consultation->getType() ?: $consultation->getNoteSeance(),
                'medecin' => trim(($consultation->getMedecin()?->getNom() ?? '') . ' ' . ($consultation->getMedecin()?->getPrenom() ?? '')),
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
            'email' => $patient->getEmail(),
            'adresse' => $patient->getAdresse(),
            'profession' => $patient->getProfession(),
            'lieuNaissance' => $patient->getLieuNaissance(),
            'numCarnet' => $patient->getNumCarnet(),
            'groupeSanguin' => $patient->getGroupeSanguin(),
            'dateInscription' => $patient->getDateInscription()->format('Y-m-d H:i'),
            'contactUrgence' => $contactUrgence,
            'smsPreferences' => $this->extractSmsPreferences($patient),
            'derniereConsultation' => $derniereConsultation,
        ];
    }

    public function getPatientWithMedicalData(int $id): ?Patient
    {
        return $this->patientRepo->findWithMedicalData($id);
    }

    public function createConsultation(array $data, ?User $triggeredBy = null): array
    {
        try {
            $isMedecinRequired = $this->globalSettingsService->isMedecinRequiredOnConsultationCreation();
            if ($isMedecinRequired && empty($data['medecin_id'])) {
                return [
                    'error' => 'Le médecin est requis pour créer la consultation.',
                    'status' => 400,
                ];
            }

            if (($data['payant'] ?? 0) == 1) {
                $insuranceEnabled = (bool) (($data['insurance_enabled'] ?? $data['insuranceEnabled'] ?? 0) == 1);
                $consultationAmount = (float) ($data['consultation_amount'] ?? 5000);
                if ($consultationAmount <= 0) {
                    $consultationAmount = 5000;
                }

                $insuranceRate = max(0, min(100, (float) ($data['insurance_rate'] ?? $data['insuranceRate'] ?? 0)));
                $insuranceAmount = (float) ($data['insurance_amount'] ?? $data['insuranceAmount'] ?? 0);

                $insuranceModeId = (int) ($data['insurance_mode_id'] ?? $data['insuranceModeId'] ?? 0);
                $insuranceMode = null;
                if ($insuranceEnabled) {
                    $insuranceMode = $this->em->getRepository(ModeDePaiement::class)->find($insuranceModeId);
                    if (!$insuranceMode) {
                        return [
                            'error' => 'Mode assurance invalide.',
                            'status' => 400,
                        ];
                    }

                    if ($insuranceRate <= 0) {
                        $insuranceRate = max(0, min(100, (float) ($insuranceMode->getCoverageRate() ?? 0)));
                    }
                }

                if ($insuranceEnabled && $insuranceAmount <= 0 && $insuranceRate > 0) {
                    $insuranceAmount = ($consultationAmount * $insuranceRate) / 100;
                }

                $patientAmount = (float) ($data['patient_amount'] ?? $data['patientAmount'] ?? $consultationAmount);

                if ($insuranceEnabled && $insuranceAmount <= 0 && $patientAmount > 0 && $patientAmount < $consultationAmount) {
                    $insuranceAmount = $consultationAmount - $patientAmount;
                }

                if ($insuranceEnabled) {
                    $patientAmount = $consultationAmount - $insuranceAmount;
                }

                $insuranceAmount = max(0, min($consultationAmount, $insuranceAmount));
                $patientAmount = max(0, min($consultationAmount - $insuranceAmount, $patientAmount));

                if ($insuranceEnabled && $insuranceAmount <= 0) {
                    return [
                        'error' => 'Le mode assurance et le montant assurance sont requis.',
                        'status' => 400,
                    ];
                }

                if ($patientAmount > 0 && !isset($data['mode_paiement_id'])) {
                    return [
                        'error' => 'Le mode de paiement est requis pour une consultation payante.',
                        'status' => 400,
                    ];
                }

                $consultation = $this->consultationRepo->NewConsultation($data, $this->patientRepo, $this->employeRepo);
                $createdPaiementId = null;
                $patientPayment = null;
                $timestamp = new DateTime();

                if ($patientAmount > 0) {
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
                    $paiement->setMontant($patientAmount);
                    $paiement->setDate($timestamp);
                    $paiement->setConsultation($consultation);
                    $paiement->setRolePaiement('patient');
                    $this->em->persist($paiement);
                    $patientPayment = $paiement;

                    $transaction = new Transaction();
                    $transaction->setType('Entrée');
                    $transaction->setMontant($patientAmount);
                    $transaction->setDateTransaction($timestamp);
                    $transaction->setDescription('Ticket de consultation #' . $consultation->getId() . ' | Part patient');
                    $transaction->setModeDePaiement($modePaiement);
                    $transaction->setConsultation($consultation);
                    $transaction->setRolePaiement('patient');
                    $transaction->markValidated();
                    $transaction->setPaiementDevis($paiement);
                    $this->em->persist($transaction);
                }

                if ($insuranceEnabled && $insuranceAmount > 0) {
                    $insuranceTx = new Transaction();
                    $insuranceTx->setType('Entrée');
                    $insuranceTx->setMontant($insuranceAmount);
                    $insuranceTx->setDateTransaction($timestamp);
                    $insuranceTx->setDescription('Ticket de consultation #' . $consultation->getId() . ' | Part assurance');
                    $insuranceTx->setModeDePaiement($insuranceMode);
                    $insuranceTx->setConsultation($consultation);
                    $insuranceTx->setRolePaiement('insurance');
                    $insuranceTx->setTauxPriseEnCharge($insuranceRate > 0 ? $insuranceRate : null);
                    $insuranceTx->markPending();
                    $this->em->persist($insuranceTx);

                    if ($this->globalSettingsService->isDirectInsurancePaymentEnabled()) {
                        $insurancePay = new PaiementDevis();
                        $insurancePay->setDevis(null);
                        $insurancePay->setMode($insuranceMode);
                        $insurancePay->setMontant($insuranceAmount);
                        $insurancePay->setDate($timestamp);
                        $insurancePay->setConsultation($consultation);
                        $insurancePay->setRolePaiement('insurance');
                        $insurancePay->setTauxPriseEnCharge($insuranceRate > 0 ? $insuranceRate : null);
                        $insuranceTx->setPaiementDevis($insurancePay);
                        $this->em->persist($insurancePay);
                    }
                }

                $this->em->flush();

                if ($patientPayment instanceof PaiementDevis && $patientPayment->getId() !== null) {
                    $createdPaiementId = $patientPayment->getId();
                } elseif ($consultation->getPaiementDevis()) {
                    $createdPaiementId = $consultation->getPaiementDevis()->getId();
                }

                $this->consultationNotificationService->notifyCreation($consultation, $triggeredBy);

                return [
                    'success' => true,
                    'status' => 200,
                    'consultation_id' => $consultation->getId(),
                    'paiement_id' => $createdPaiementId,
                ];
            }

            $consultation = $this->consultationRepo->NewConsultation($data, $this->patientRepo, $this->employeRepo);

            $this->consultationNotificationService->notifyCreation($consultation, $triggeredBy);

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

    public function createRdv(array $data, ?User $actor = null): array
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

            $this->rdvNotificationService->notifyCreation($rdv, $actor);

            return ['success' => true, 'status' => 201, 'rdv_id' => $rdv->getId()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'status' => 500];
        }
    }

    private function notifyPatientCreation(Patient $patient, ?User $actor = null): void
    {
        $recipients = $this->notificationRecipientResolver->adminsAndReceptionists($actor);

        if ($recipients === []) {
            return;
        }

        $patientName = trim($patient->getFullName() ?? '') ?: sprintf('patient #%d', $patient->getId());
        $author = $actor?->getUsername() ?? 'le système';
        $message = sprintf('Nouveau patient %s ajouté par %s.', $patientName, $author);

        $this->eventDispatcher->dispatch(
            new EntityActionEvent(
                $patient,
                'created',
                ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE'],
                $actor,
                [
                    'message' => $message,
                    'priority' => 'info',
                    'type' => 'success',
                    'link' => '/reception/patients',
                ],
            )
        );
    }

    public function getPatientsPageContext(): array
    {
        return [
            'salles' => $this->salleRepo->findAll(),
        ];
    }

    public function getPatientPortalAccountData(int $id): array
    {
        $patient = $this->patientRepo->find($id);
        if (!$patient) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        }

        return [
            'success' => true,
            'account' => $this->formatPortalAccount($patient),
        ];
    }

    public function createPatientPortalAccount(int $id): array
    {
        $patient = $this->patientRepo->find($id);
        if (!$patient) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        }

        if ($patient->getPortalUser() instanceof User) {
            return [
                'success' => true,
                'message' => 'Compte utilisateur deja lie au patient.',
                'account' => $this->formatPortalAccount($patient),
            ];
        }

        $username = $this->buildUniquePatientUsername($patient);
        $user = new User();
        $user->setUsername($username);
        $user->setRoles(['ROLE_PATIENT']);
        $user->setActive(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, '123'));

        $patient->setPortalUser($user);

        $this->em->persist($user);
        $this->em->persist($patient);
        $this->em->flush();

        return [
            'success' => true,
            'message' => 'Compte patient cree avec succes.',
            'account' => $this->formatPortalAccount($patient),
        ];
    }

    public function resetPatientPortalPassword(int $id): array
    {
        $patient = $this->patientRepo->find($id);
        if (!$patient) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        }

        $user = $patient->getPortalUser();
        if (!$user instanceof User) {
            return ['error' => 'Aucun compte utilisateur lie a ce patient', 'status' => 404];
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, '123'));
        $this->em->persist($user);
        $this->em->flush();

        return [
            'success' => true,
            'message' => 'Mot de passe reinitialise a 123.',
            'account' => $this->formatPortalAccount($patient),
        ];
    }

    public function togglePatientPortalAccount(int $id, bool $active): array
    {
        $patient = $this->patientRepo->find($id);
        if (!$patient) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        }

        $user = $patient->getPortalUser();
        if (!$user instanceof User) {
            return ['error' => 'Aucun compte utilisateur lie a ce patient', 'status' => 404];
        }

        $user->setActive($active);
        $this->em->persist($user);
        $this->em->flush();

        return [
            'success' => true,
            'message' => $active ? 'Compte active.' : 'Compte desactive.',
            'account' => $this->formatPortalAccount($patient),
        ];
    }

    private function formatPortalAccount(Patient $patient): ?array
    {
        $user = $patient->getPortalUser();
        if (!$user instanceof User) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'active' => $user->isActive(),
            'roles' => $user->getRoles(),
            'defaultPassword' => '123',
        ];
    }

    private function buildUniquePatientUsername(Patient $patient): string
    {
        $raw = sprintf(
            '%s%s%s%s',
            $patient->getNom() ?? '',
            $patient->getPrenom() ?? '',
            $patient->getAge() ?? 0,
            $patient->getDateInscription()?->format('Ymd') ?? (new DateTime())->format('Ymd')
        );

        $base = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw) ?: $raw));
        if ($base === '') {
            $base = 'patient' . ($patient->getId() ?? (new DateTime())->format('His'));
        }

        $username = $base;
        $i = 1;
        while ($this->userRepo->findOneBy(['username' => $username]) !== null) {
            $i++;
            $username = $base . $i;
        }

        return $username;
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
                $age = $patient->getDateNaissance()->diff(new DateTime())->y;
            } catch (\Exception) {
                $age = 'Néant';
            }
        }

        $contact = $patient->getContactUrgence();
        $contactUrgence = $contact ? [
            'nom' => $contact->getNom(),
            'lienParente' => $contact->getLienParente(),
            'telephone' => $contact->getTelephone(),
        ] : null;

        $allergies = [];
        foreach ($patient->getAllergies() as $allergy) {
            $allergies[] = [
                'id' => $allergy->getId(),
                'libelle' => $allergy->getLibelle(),
                'description' => $allergy->getDescription(),
            ];
        }

        $antecedents = [];
        foreach ($patient->getAntecedents() as $antecedent) {
            $antecedents[] = [
                'id' => $antecedent->getId(),
                'type' => $antecedent->getType(),
                'description' => $antecedent->getDescription(),
                'date' => $antecedent->getDateEnregistrement()?->format('Y-m-d'),
            ];
        }

        $derniereConsultation = null;
        $latestConsultation = $this->resolveLatestConsultation($patient);
        if ($latestConsultation) {
            $consultation = $latestConsultation;
            $derniereConsultation = [
                'date' => $consultation->getCreatedAt()?->format('Y-m-d H:i'),
                'motif' => $consultation->getType() ?: $consultation->getNoteSeance(),
                'medecin' => $consultation->getMedecin()?->getNom().' '.$consultation->getMedecin()?->getPrenom(),
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
        foreach ($patient->getFichesMedicales() as $ficheMedicale) {
            $ficheData = $this->ficheMedicaleService->getFicheJson($ficheMedicale->getId());
            $ficheData['version'] = 2;
            $ficheData['dateCreation'] = $ficheData['createdAt']
                ?? $ficheMedicale->getCreatedAt()?->format('Y-m-d H:i:s');
            $fiches[] = $ficheData;
        }

        $factures = $this->cashdeskService->listDevisImpayesByPatient($patient->getId());
        $paiements = $this->cashdeskService->listPaiementsDevisByPatients($patient);
        

        return [
            'id' => $patient->getId(),
            'nom' => $patient->getNom(),
            'prenom' => $patient->getPrenom(),
            'dateNaissance' => $patient->getDateNaissance() ? $patient->getDateNaissance()->format('Y-m-d') : null,
            'age' => $age,
            'sexe' => $patient->getSexe(),
            'telephone' => $patient->getTelephone(),
            'email' => $patient->getEmail(),
            'adresse' => $patient->getAdresse(),
            'profession' => $patient->getProfession(),
            'lieuNaissance' => $patient->getLieuNaissance(),
            'numCarnet' => $patient->getNumCarnet(),
            'groupeSanguin' => $patient->getGroupeSanguin(),
            'dateInscription' => $patient->getDateInscription()->format('Y-m-d H:i'),
            'contactUrgence' => $contactUrgence,
            'portalAccount' => $this->formatPortalAccount($patient),
            'smsPreferences' => $this->extractSmsPreferences($patient),
            'allergies' => $allergies,
            'antecedents' => $antecedents,
            'derniereConsultation' => $derniereConsultation,
            'rdvs' => $rdvs,
            'fiches' => $fiches,
            'factures' => $factures,
            'paiements' => $paiements,
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

        $simples = ['nom', 'prenom', 'sexe', 'telephone', 'adresse', 'numCarnet', 'groupeSanguin', 'email', 'profession', 'lieuNaissance'];
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

        $this->applySmsPreferences($patient, $payload);

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
            $hasContact = !empty($c['nom']) || !empty($c['telephone']) || !empty($c['lienParente']);
            $existingContact = $patient->getContactUrgence();

            if ($hasContact) {
                if (!$existingContact) {
                    $existingContact = new ContactUrgence();
                    $existingContact->setPatient($patient);
                    $patient->setContactUrgence($existingContact);
                }
                $existingContact->setNom($c['nom'] ?? null);
                $existingContact->setTelephone($c['telephone'] ?? null);
                $existingContact->setLienParente($c['lienParente'] ?? null);
                $this->em->persist($existingContact);
            } elseif ($existingContact) {
                $patient->setContactUrgence(null);
                $this->em->remove($existingContact);
            }
        }

        $this->em->persist($patient);
        $this->em->flush();

        return ['success' => true];
    }

    /**
     * @return array<string, bool>
     */
    private function extractSmsPreferences(Patient $patient): array
    {
        return [
            'patientCreated' => $patient->isSmsPatientCreated(),
            'receipt' => $patient->isSmsReceipt(),
            'ticket' => $patient->isSmsTicket(),
            'invoice' => $patient->isSmsInvoice(),
            'appointmentReminder' => $patient->isSmsAppointmentReminder(),
            'unsubscribed' => $patient->isSmsUnsubscribed(),
            'blacklisted' => $patient->isSmsBlacklisted(),
        ];
    }

    private function applySmsPreferences(Patient $patient, array $data): void
    {
        $sms = isset($data['smsPreferences']) && is_array($data['smsPreferences'])
            ? $data['smsPreferences']
            : $data;

        $mappings = [
            'smsPatientCreated' => 'setSmsPatientCreated',
            'patientCreated' => 'setSmsPatientCreated',
            'smsReceipt' => 'setSmsReceipt',
            'receipt' => 'setSmsReceipt',
            'smsTicket' => 'setSmsTicket',
            'ticket' => 'setSmsTicket',
            'smsInvoice' => 'setSmsInvoice',
            'invoice' => 'setSmsInvoice',
            'smsAppointmentReminder' => 'setSmsAppointmentReminder',
            'appointmentReminder' => 'setSmsAppointmentReminder',
            'smsUnsubscribed' => 'setSmsUnsubscribed',
            'unsubscribed' => 'setSmsUnsubscribed',
            'smsBlacklisted' => 'setSmsBlacklisted',
            'blacklisted' => 'setSmsBlacklisted',
        ];

        foreach ($mappings as $key => $setter) {
            if (!array_key_exists($key, $sms)) {
                continue;
            }
            $patient->$setter((bool) $sms[$key]);
        }
    }

    public function addAntecedent(int $patientId, array $payload): array
    {
        $patient = $this->patientRepo->find($patientId);
        if (!$patient) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        }

        $antecedent = new Antecedent();
        $antecedent
            ->setType($payload['type'] ?? null)
            ->setDescription($payload['description'] ?? null)
            ->setDateEnregistrement(new DateTimeImmutable())
            ->setPatient($patient);

        $this->em->persist($antecedent);
        $patient->addAntecedent($antecedent);
        $this->em->flush();

        return [
            'success' => true,
            'antecedent' => [
                'id' => $antecedent->getId(),
                'type' => $antecedent->getType(),
                'description' => $antecedent->getDescription(),
                'dateEnregistrement' => $antecedent->getDateEnregistrement()?->format('Y-m-d'),
            ]
        ];
    }

    public function deleteAntecedent(int $patientId, int $antecedentId): array
    {
        $patient = $this->patientRepo->find($patientId);
        if (!$patient) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        }

        $antecedent = $this->em->getRepository(Antecedent::class)->find($antecedentId);
        if (!$antecedent || $antecedent->getPatient()?->getId() !== $patientId) {
            return ['error' => 'Antécédent introuvable', 'status' => 404];
        }

        $this->em->remove($antecedent);
        $this->em->flush();

        return ['success' => true];
    }

    public function addAllergy(int $patientId, array $payload): array
    {
        $patient = $this->patientRepo->find($patientId);
        if (!$patient) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        }

        $allergy = new Allergy();
        $allergy
            ->setLibelle($payload['libelle'] ?? null)
            ->setDescription($payload['description'] ?? null)
            ->setPatient($patient);

        $this->em->persist($allergy);
        $patient->addAllergy($allergy);
        $this->em->flush();

        return [
            'success' => true,
            'allergy' => [
                'id' => $allergy->getId(),
                'libelle' => $allergy->getLibelle(),
                'description' => $allergy->getDescription(),
            ]
        ];
    }

    public function deleteAllergy(int $patientId, int $allergyId): array
    {
        $patient = $this->patientRepo->find($patientId);
        if (!$patient) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        }

        $allergy = $this->em->getRepository(Allergy::class)->find($allergyId);
        if (!$allergy || $allergy->getPatient()?->getId() !== $patientId) {
            return ['error' => 'Allergie introuvable', 'status' => 404];
        }

        $this->em->remove($allergy);
        $this->em->flush();

        return ['success' => true];
    }

    public function getPrintInfosPersoContext(int $id): ?Patient
    {
        return $this->patientRepo->find($id);
    }

    public function getPrintInfosPersoData(int $id): ?array
    {
        $patient = $this->patientRepo->find($id);
        if (!$patient) {
            return null;
        }

        $contact = $patient->getContactUrgence();
        $contactUrgence = $contact ? [
            'nom' => $contact->getNom(),
            'telephone' => $contact->getTelephone(),
            'lienParente' => $contact->getLienParente(),
        ] : null;

        $allergies = array_map(fn (Allergy $a) => [
            'id' => $a->getId(),
            'libelle' => $a->getLibelle(),
            'description' => $a->getDescription(),
        ], $patient->getAllergies()->toArray());

        $antecedents = array_map(fn (Antecedent $a) => [
            'id' => $a->getId(),
            'type' => $a->getType(),
            'description' => $a->getDescription(),
            'dateEnregistrement' => $a->getDateEnregistrement()?->format('Y-m-d'),
        ], $patient->getAntecedents()->toArray());

        return [
            'id' => $patient->getId(),
            'nom' => $patient->getNom(),
            'prenom' => $patient->getPrenom(),
            'dateNaissance' => $patient->getDateNaissance()?->format('Y-m-d'),
            'sexe' => $patient->getSexe(),
            'telephone' => $patient->getTelephone(),
            'email' => $patient->getEmail(),
            'adresse' => $patient->getAdresse(),
            'profession' => $patient->getProfession(),
            'lieuNaissance' => $patient->getLieuNaissance(),
            'numCarnet' => $patient->getNumCarnet(),
            'groupeSanguin' => $patient->getGroupeSanguin(),
            'dateInscription' => $patient->getDateInscription()?->format('Y-m-d H:i'),
            'contactUrgence' => $contactUrgence,
            'allergies' => $allergies,
            'antecedents' => $antecedents,
        ];
    }

    public function getPrintFicheContext(int $patientId, int $ficheId): ?array
    {
        $patient = $this->patientRepo->find($patientId);
        if (!$patient) {
            return null;
        }

        $ficheMedicale = $this->em->getRepository(FicheMedicale::class)->find($ficheId);
        if ($ficheMedicale && $ficheMedicale->getPatient()->getId() === $patientId) {
            return [
                'patient' => $patient,
                'fiche' => $ficheMedicale,
                'version' => 2,
            ];
        }

        return null;
    }

    public function getPrintFicheData(int $patientId, int $ficheId): ?array
    {
        $context = $this->getPrintFicheContext($patientId, $ficheId);
        if (!$context) {
            return null;
        }

        /** @var Patient $patient */
        $patient = $context['patient'];
        /** @var FicheMedicale $fiche */
        $fiche = $context['fiche'];

        $consultations = array_map(function (Consultation $consultation) {
            $actes = array_map(fn (ActeMedical $a) => [
                'dent' => $a->getDent(),
                'type' => $a->getType(),
                'description' => $a->getDescription(),
                'prix' => $a->getPrix(),
                'quantite' => $a->getQuantite(),
            ], $consultation->getActes()->toArray());

            $ordonnances = array_map(function (Ordonnance $ord) {
                return [
                    'id' => $ord->getId(),
                    'date' => $ord->getDate()?->format('Y-m-d'),
                    'medecinNom' => $ord->getMedecinNom(),
                    'note' => $ord->getNote(),
                    'lignes' => array_map(fn(OrdonnanceLigne $l) => [
                        'designation' => $l->getDesignation(),
                        'posologie' => $l->getPosologie(),
                        'frequence' => $l->getFrequence(),
                        'duree' => $l->getDuree(),
                        'quantite' => $l->getQuantite(),
                        'instructions' => $l->getInstructions(),
                    ], $ord->getLignes()->toArray()),
                ];
            }, $consultation->getOrdonnances()->toArray());

            return [
                'id' => $consultation->getId(),
                'type' => $consultation->getType(),
                'noteSeance' => $consultation->getNoteSeance(),
                'createdAt' => $consultation->getCreatedAt()?->format('Y-m-d H:i'),
                'medecin' => $consultation->getMedecin() ? [
                    'nom' => $consultation->getMedecin()?->getNom().' '.$consultation->getMedecin()?->getPrenom(),
                ] : null,
                'infirmier' => $consultation->getInfirmier() ? [
                    'nom' => $consultation->getInfirmier()?->getNom().' '.$consultation->getInfirmier()?->getPrenom(),
                ] : null,
                'salle' => $consultation->getSalle() ? [
                    'nom' => $consultation->getSalle()?->getNom(),
                ] : null,
                'actes' => $actes,
                'ordonnances' => $ordonnances,
            ];
        }, $fiche->getConsultations()->toArray());

        $ficheData = $this->ficheMedicaleService->getFicheJson($fiche->getId());
        $ficheData['consultations'] = $consultations;

        return [
            'version' => 2,
            'patient' => [
                'id' => $patient->getId(),
                'nom' => $patient->getNom(),
                'prenom' => $patient->getPrenom(),
                'age' => $patient->getAge(),
                'telephone' => $patient->getTelephone(),
            ],
            'fiche' => $ficheData,
        ];
    }
}
