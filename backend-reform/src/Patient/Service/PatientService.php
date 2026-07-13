<?php

namespace App\Patient\Service;

use App\Billing\Entity\Assurance;
use App\Billing\Entity\FactureAssurance;
use App\Billing\Entity\ModeDePaiement;
use App\Billing\Entity\Paiement;
use App\Billing\Entity\Transaction;
use App\Billing\Repository\AssuranceRepository;
use App\CareDelivery\Entity\ActeMedical;
use App\CareDelivery\Entity\Consultation;
use App\CareDelivery\Entity\Ordonnance;
use App\CareDelivery\Entity\OrdonnanceLigne;
use App\CareDelivery\Service\ConsultationNotificationService;
use App\ClinicalRecord\Entity\FicheMedicale;
use App\ClinicalRecord\Service\FicheMedicaleService;
use App\Communication\Entity\SmsQueue;
use App\Communication\Repository\SmsQueueRepository;
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
use App\Patient\Entity\PatientAssuranceProfile;
use App\Patient\Repository\PatientRepository;
use App\CareDelivery\Repository\ConsultationRepository;
use App\CareDelivery\Service\ConsultationService;
use App\Scheduling\Entity\Rdv;
use App\Scheduling\Repository\SalleRepository;
use App\Scheduling\Service\RdvNotificationService;
use App\Settings\Service\GlobalSettingsService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use App\Billing\Service\CashdeskService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class PatientService
{
    /**
     * @return list<\DateTimeImmutable>
     */
    private function buildAppointmentReminderDates(\DateTimeImmutable $rdvAt, int $daysBefore, string $recurrence): array
    {
        $daysBefore = max(0, $daysBefore);
        $firstSendAt = $rdvAt->modify(sprintf('-%d days', $daysBefore));
        if (!$firstSendAt instanceof \DateTimeImmutable) {
            return [];
        }

        if ($recurrence === 'none') {
            return [$firstSendAt];
        }

        $step = match ($recurrence) {
            'daily' => '+1 day',
            'every_2_days' => '+2 days',
            'weekly' => '+1 week',
            default => null,
        };

        if ($step === null) {
            return [$firstSendAt];
        }

        $dates = [];
        $cursor = $firstSendAt;
        $maxOccurrences = 14;

        while ($cursor < $rdvAt && count($dates) < $maxOccurrences) {
            $dates[] = $cursor;
            $cursor = $cursor->modify($step);
        }

        return $dates;
    }

    private function queueAppointmentRemindersForRdv(Rdv $rdv, array $smsReminder, string $cabinetName = 'ORODENT'): int
    {
        $enabled = ($smsReminder['enabled'] ?? true) !== false;
        if (!$enabled) {
            return 0;
        }

        $patient = $rdv->getPatient();
        $rdvAt = $rdv->getDateRdv();
        if (!$patient instanceof Patient || !$rdvAt instanceof DateTime) {
            return 0;
        }

        $daysBefore = max(0, (int) ($smsReminder['daysBefore'] ?? 1));
        $recurrence = (string) ($smsReminder['recurrence'] ?? 'none');
        $dates = $this->buildAppointmentReminderDates(DateTimeImmutable::createFromMutable($rdvAt), $daysBefore, $recurrence);
        $now = new DateTimeImmutable();

        $variables = [
            'patient_name' => trim(($patient->getPrenom() ?? '') . ' ' . ($patient->getNom() ?? '')),
            'date' => $rdvAt->format('d/m/Y'),
            'time' => $rdvAt->format('H:i'),
            'cabinet_name' => $cabinetName,
        ];

        $queued = 0;
        foreach ($dates as $index => $sendAt) {
            if ($sendAt <= $now) {
                continue;
            }

            $result = $this->smsService->queueTemplateForPatient(
                $patient,
                'appointment_reminder',
                $variables,
                'appointment-auto',
                $sendAt,
                [
                    'rdvId' => $rdv->getId(),
                    'recurrence' => $recurrence,
                    'daysBefore' => $daysBefore,
                    'occurrenceIndex' => $index + 1,
                    'occurrenceCount' => count($dates),
                ]
            );

            if (($result['success'] ?? false) === true) {
                ++$queued;
            }
        }

        return $queued;
    }

    public function __construct(
        private EntityManagerInterface $em,
        private PatientRepository $patientRepo,
        private AssuranceRepository $assuranceRepo,
        private SalleRepository $salleRepo,
        private ConsultationRepository $consultationRepo,
        private ConsultationService $consultationService,
        private EmployeRepository $employeRepo,
        private ConsultationNotificationService $consultationNotificationService,
        private NotificationRecipientResolver $notificationRecipientResolver,
        private RdvNotificationService $rdvNotificationService,
        private UserRepository $userRepo,
        private UserPasswordHasherInterface $passwordHasher,
        private CashdeskService $cashdeskService,
        private FicheMedicaleService $ficheMedicaleService,
        private SmsService $smsService,
        private SmsQueueRepository $smsQueueRepository,
        private GlobalSettingsService $globalSettingsService,
        private CacheInterface $cache,
        private EventDispatcherInterface $eventDispatcher,
        private FocusRealtimePublisher $focusRealtimePublisher,
    ) {
    }

    private function resolveLatestConsultation(Patient $patient): ?Consultation
    {
        $latest = $this->consultationRepo->findLatestClosedByPatient($patient);

        if ($latest !== null) {
            return $latest;
        }

        $fallback = $patient->getDerniereConsultation();
        if ($fallback instanceof Consultation && (int) $fallback->getStatut() === 1) {
            return $fallback;
        }

        return null;
    }

    private function clearPatientsCache(): void
    {
        if ($this->cache instanceof CacheItemPoolInterface) {
            $this->cache->clear();
        }
    }

    private function findActivePatient(int $id): ?Patient
    {
        $patient = $this->patientRepo->find($id);

        if (!$patient instanceof Patient || $patient->isDeleted()) {
            return null;
        }

        return $patient;
    }

    private function formatPatientSummary(Patient $patient): array
    {
        $contact = $patient->getContactUrgence();
        $consultation = $this->resolveLatestConsultation($patient);
        $photo = $this->resolvePatientPhoto($patient);

        return [
            'id' => $patient->getId(),
            'nom' => $patient->getNom(),
            'prenom' => $patient->getPrenom(),
            'fullname' => $patient->getFullName(),
            'photo' => $photo,
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
            'referencement' => $patient->getReferencement() ?? '',
            'contactUrgence' => $contact ? [
                'nom' => $contact->getNom(),
                'telephone' => $contact->getTelephone(),
                'lienParente' => $contact->getLienParente(),
            ] : null,
            'smsPreferences' => $this->extractSmsPreferences($patient),
            'insuranceProfile' => $this->formatInsuranceProfile($patient),
            'derniereConsultation' => $consultation ? [
                'id' => $consultation->getId(),
                'date' => $consultation->getCreatedAt()?->format('Y-m-d H:i'),
                'motif' => $consultation->getNoteSeance(),
                'statut' => $consultation->getStatut(),
            ] : null,
            'impayees' => $this->getPatientImpayees($patient->getId()),
            'archiveFiles' => $patient->getArchiveFiles() ? array_map(fn (array $file) => [
                'nom' => $file['nom'] ?? 'Fichier',
                'url' => $file['url'] ?? null,
            ], $patient->getArchiveFiles()) : [],
        ];
    }

    private function resolvePatientPhoto(Patient $patient): ?string
    {
        $photo = $patient->getPhoto();

        return is_string($photo) && $photo !== '' ? $photo : null;
    }

    private function resolvePatientPhotoFilePath(string $uploadDir, string $photoPath): ?string
    {
        if ($photoPath === '' || !str_starts_with($photoPath, '/uploads/')) {
            return null;
        }

        return rtrim(dirname($uploadDir), '/\\') . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $photoPath), '/\\');
    }

    private function uploadPatientPhoto(UploadedFile $file, string $uploadDir, ?string $currentPhoto = null, ?int $patientId = null): string
    {
        $targetDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . 'patients' . DIRECTORY_SEPARATOR  . ($patientId ?? 'unknown') . DIRECTORY_SEPARATOR . 'photos';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Impossible de creer le dossier des photos patients.');
        }

        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin'));
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'bin';
        $filename = sprintf('patient_%s.%s', bin2hex(random_bytes(8)), $extension);
        $file->move($targetDir, $filename);

        if ($currentPhoto) {
            $previousFile = $this->resolvePatientPhotoFilePath($uploadDir, $currentPhoto);
            if ($previousFile && is_file($previousFile)) {
                @unlink($previousFile);
            }
        }

        return '/uploads/patients/' . ($patientId ?? 'unknown') . '/photos/' . $filename;
    }

    public function getPatientImpayees(int $id): int
    {
        $factures = $this->cashdeskService->listFacturesImpayeesByPatient($id);
        $impayees = 0;
        foreach ($factures as $facture) {
            // You can add more detailed info if needed
            $impayees += $facture['reste'];
        }
        return $impayees;
    }

    private function resolveMedecinFromUser(?object $user): array
    {
        if (!$user) {
            return ['error' => 'Utilisateur non authentifié', 'status' => 401];
        }

        $employe = $this->employeRepo->findOneBy(['user' => $user]);
        if (!$employe) {
            return ['error' => 'Aucun employé associé', 'status' => 404];
        }

        return ['employe' => $employe];
    }

    public function listPatientsCollection(
        ?object $user = null,
        bool $medecinOnly = false,
        bool $paginated = false,
        int $page = 1,
        int $limit = 10,
        ?string $query = null,
        ?string $sortField = null,
        ?string $sortOrder = null
    ): array {
        $resolvedPage = max(1, $page);
        $resolvedLimit = max(1, min($limit, 100));
        $resolvedSortOrder = strtolower($sortOrder ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $medecin = null;
        if ($medecinOnly) {
            $resolved = $this->resolveMedecinFromUser($user);
            if (isset($resolved['error'])) {
                return $resolved;
            }
            $medecin = $resolved['employe'];
        }

        if (!$paginated) {
            $patients = $medecinOnly
                ? $this->patientRepo->findPatientsByMedecin($medecin, $query, $sortField, $resolvedSortOrder)
                : $this->patientRepo->findBy(['deletedAt' => null], ['nom' => 'ASC']);

            return array_values(array_map(fn (Patient $patient) => $this->formatPatientSummary($patient), $patients));
        }

        $cacheScope = $medecinOnly ? sprintf('medecin.%d', $medecin->getId()) : 'global';
        $cacheKey = sprintf(
            'patients.collection.%s.%d.%d.%s.%s.%s',
            $cacheScope,
            $resolvedPage,
            $resolvedLimit,
            sha1((string) $query),
            $sortField ?? 'default',
            $resolvedSortOrder
        );

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($medecinOnly, $medecin, $resolvedPage, $resolvedLimit, $query, $sortField, $resolvedSortOrder) {
            $item->expiresAfter(60);

            $result = $medecinOnly
                ? $this->patientRepo->paginatePatientsByMedecin($medecin, $resolvedPage, $resolvedLimit, $query, $sortField, $resolvedSortOrder)
                : $this->patientRepo->paginatePatients($resolvedPage, $resolvedLimit, $query, $sortField, $resolvedSortOrder);

            $items = array_map(fn (Patient $patient) => $this->formatPatientSummary($patient), $result['items']);

            return [
                'items' => $items,
                'total' => $result['total'],
                'page' => $resolvedPage,
                'limit' => $resolvedLimit,
                'sortField' => $sortField,
                'sortOrder' => $resolvedSortOrder,
            ];
        });
    }

    public function listPatients(): array
    {
        return $this->listPatientsCollection();
    }

    public function listPatientsPaginated(int $page, int $limit, ?string $query = null, ?string $sortField = null, ?string $sortOrder = null): array
    {
        return $this->listPatientsCollection(
            paginated: true,
            page: $page,
            limit: $limit,
            query: $query,
            sortField: $sortField,
            sortOrder: $sortOrder
        );
    }

    public function listPatientsByMedecin(?object $user): array
    {
        return $this->listPatientsCollection(
            user: $user,
            medecinOnly: true
        );
    }

    public function listPatientsByMedecinPaginated(?object $user, int $page, int $limit, ?string $query = null, ?string $sortField = null, ?string $sortOrder = null): array
    {
        return $this->listPatientsCollection(
            user: $user,
            medecinOnly: true,
            paginated: true,
            page: $page,
            limit: $limit,
            query: $query,
            sortField: $sortField,
            sortOrder: $sortOrder
        );
    }

    public function listDeletedPatientsPaginated(int $page, int $limit, ?string $query = null): array
    {
        $resolvedPage = max(1, $page);
        $resolvedLimit = max(1, min($limit, 100));

        $result = $this->patientRepo->paginateDeletedPatients($resolvedPage, $resolvedLimit, $query);
        $items = array_map(fn (Patient $patient) => [
            ...$this->formatPatientSummary($patient),
            'deletedAt' => $patient->getDeletedAt()?->format('Y-m-d H:i:s'),
        ], $result['items']);

        return [
            'items' => $items,
            'total' => $result['total'],
            'page' => $resolvedPage,
            'limit' => $resolvedLimit,
        ];
    }

    public function getOverviewStats(?object $user = null, bool $medecinOnly = false): array
    {
        $medecin = null;
        if ($medecinOnly) {
            $resolved = $this->resolveMedecinFromUser($user);
            if (isset($resolved['error'])) {
                return $resolved;
            }
            $medecin = $resolved['employe'];
        }

        $todayStart = new DateTimeImmutable('today 00:00:00');
        $todayEnd = new DateTimeImmutable('today 23:59:59');
        $monthStart = new DateTimeImmutable('first day of this month 00:00:00');
        $now = new DateTimeImmutable();

        $totalQb = $this->patientRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.deletedAt IS NULL');

        $consultationsTodayQb = $this->consultationRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->innerJoin('c.patient', 'cp')
            ->andWhere('cp.deletedAt IS NULL')
            ->andWhere('c.CreatedAt BETWEEN :todayStart AND :todayEnd')
            ->setParameter('todayStart', $todayStart)
            ->setParameter('todayEnd', $todayEnd);

        $upcomingRdvsQb = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Rdv::class, 'r')
            ->innerJoin('r.patient', 'rp')
            ->andWhere('rp.deletedAt IS NULL')
            ->andWhere('r.dateRdv >= :now')
            ->andWhere('r.statut IN (0, 1)')
            ->setParameter('now', $now);

        $newPatientsMonthQb = $this->patientRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.dateInscription >= :monthStart')
            ->setParameter('monthStart', $monthStart);

        $referralsQb = $this->patientRepo->createQueryBuilder('p')
            ->select('p.referencement AS source, COUNT(p.id) AS cnt')
            ->andWhere('p.deletedAt IS NULL')
            ->groupBy('p.referencement')
            ->orderBy('cnt', 'DESC');

        if ($medecin instanceof Employe) {
            $medecinScope = 'EXISTS (SELECT 1 FROM ' . Consultation::class . ' c2 WHERE c2.patient = p AND c2.medecin = :medecin) OR EXISTS (SELECT 1 FROM ' . Rdv::class . ' r2 WHERE r2.patient = p AND r2.medecin = :medecin)';
            $totalQb->andWhere($medecinScope)->setParameter('medecin', $medecin);
            $newPatientsMonthQb->andWhere($medecinScope)->setParameter('medecin', $medecin);
            $referralsQb->andWhere($medecinScope)->setParameter('medecin', $medecin);

            $consultationsTodayQb
                ->andWhere('c.medecin = :medecin')
                ->setParameter('medecin', $medecin);

            $upcomingRdvsQb
                ->andWhere('r.medecin = :medecin')
                ->setParameter('medecin', $medecin);
        }

        $referralRows = $referralsQb->getQuery()->getArrayResult();
        $referrals = array_map(static fn (array $row): array => [
            'source' => ($row['source'] ?? '') !== '' ? (string) $row['source'] : 'Non renseigné',
            'count' => (int) ($row['cnt'] ?? 0),
        ], $referralRows);

        return [
            'totalPatients' => (int) $totalQb->getQuery()->getSingleScalarResult(),
            'consultationsToday' => (int) $consultationsTodayQb->getQuery()->getSingleScalarResult(),
            'upcomingAppointments' => (int) $upcomingRdvsQb->getQuery()->getSingleScalarResult(),
            'newPatientsThisMonth' => (int) $newPatientsMonthQb->getQuery()->getSingleScalarResult(),
            'referrals' => $referrals,
        ];
    }

    public function softDeletePatient(int $id, ?User $actor = null): array
    {
        $patient = $this->findActivePatient($id);
        if (!$patient) {
            return ['error' => 'Patient non trouvé', 'status' => 404];
        }

        $activeConsultations = $this->consultationRepo->findBy([
            'patient' => $patient,
            'statut' => 0,
        ]);

        foreach ($activeConsultations as $consultation) {
            if ($patient->getDerniereConsultation()?->getId() === $consultation->getId()) {
                $patient->setDerniereConsultation(null);
                $this->em->flush();
            }

            $this->consultationService->deleteConsultation((int) $consultation->getId(), $actor);
        }

        $patient->setDeletedAt(new DateTimeImmutable());
        $this->em->persist($patient);
        $this->em->flush();
        $this->clearPatientsCache();
        $this->focusRealtimePublisher->publishPatientRefresh($patient, 'deleted');

        return [
            'success' => true,
            'message' => 'Patient déplacé dans la corbeille.',
        ];
    }

    public function restorePatient(int $id): array
    {
        $patient = $this->patientRepo->find($id);
        if (!$patient instanceof Patient || !$patient->isDeleted()) {
            return ['error' => 'Patient introuvable dans la corbeille', 'status' => 404];
        }

        $patient->setDeletedAt(null);
        $this->em->persist($patient);
        $this->em->flush();
        $this->clearPatientsCache();
        $this->focusRealtimePublisher->publishPatientRefresh($patient, 'restored');

        return [
            'success' => true,
            'message' => 'Patient restauré avec succès.',
        ];
    }

    public function listPatientConsultations(int $patientId): array
    {
        $patient = $this->findActivePatient($patientId);
        if (!$patient) {
            return [];
        }

        $consultations = $this->consultationRepo->findConsultationsByPatient($patientId);

        return array_map(function (Consultation $consultation) {
            $facture = $consultation->getFacture();

            return [
                'id' => $consultation->getId(),
                'date' => $consultation->getCreatedAt()?->format('Y-m-d H:i'),
                'statut' => $consultation->getStatut(),
                'medecin' => $consultation->getMedecin()?->getFullName(),
                'factureMontant' => $facture?->getMontantTotal(),
                'factureStatut' => $facture?->isReglee() ? 1 : 0,
            ];
        }, $consultations);
    }

    public function addPatient(array $data, ?User $actor = null): array
    {
        if (!isset($data['nom'], $data['prenom'], $data['sexe'], $data['telephone'])) {
            return ['error' => 'ParamÃ¨tres obligatoires manquants', 'status' => 400];
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
            $patient->setReferencement((string) ($data['referencement'] ?? ''));
            $this->applySmsPreferences($patient, $data);
            $this->applyInsuranceProfile($patient, $data);

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

            if ($this->globalSettingsService->shouldAutoCreatePortalAccountOnPatientCreation()) {
                $this->ensurePatientPortalAccount($patient);
            }

            $this->em->persist($patient);
            $this->em->flush();
            $this->clearPatientsCache();

            $this->notifyPatientCreation($patient, $actor);
            $this->focusRealtimePublisher->publishPatientRefresh($patient, 'created');
            $this->smsService->queueTemplateForPatient($patient, 'patient_created', [
                'patient_name' => trim(($patient->getPrenom() ?? '') . ' ' . ($patient->getNom() ?? '')),
                'cabinet_name' => 'ORODENT',
            ], 'patient-created');

            return [
                'success' => true,
                'status' => 201,
                'patientId' => $patient->getId(),
                'portalAccount' => $this->formatPortalAccount($patient),
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => $e->getMessage(), 'status' => 400];
        } catch (\Exception $e) {
            return ['error' => 'Erreur : ' . $e->getMessage(), 'status' => 500];
        }
    }

    public function updatePatient(
                int $id,
                array $data,
                ?UploadedFile $photo = null,
                ?string $uploadDir = null,
                array $uploadedArchiveFiles = [],
                array $existingArchiveFiles = []
            ): array {
        try {
            $patient = $this->findActivePatient($id);
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
            if (array_key_exists('referencement', $data)) {
                $patient->setReferencement((string) ($data['referencement'] ?? ''));
            }
            $this->applySmsPreferences($patient, $data);
            $this->applyInsuranceProfile($patient, $data);
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

            if ($photo instanceof UploadedFile) {
                if ($uploadDir === null || $uploadDir === '') {
                    throw new \InvalidArgumentException('Dossier upload manquant pour la photo patient.');
                }
                $patient->setPhoto($this->uploadPatientPhoto($photo, $uploadDir, $patient->getPhoto(), $patient->getId()));
            }

            // ========== GESTION DES FICHIERS ARCHIVES ==========
            $finalArchiveFiles = $existingArchiveFiles; // on garde les anciens par défaut

            if (!empty($uploadedArchiveFiles)) {
                $archiveBaseDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . 'patients' . DIRECTORY_SEPARATOR . 'archive';
                $patientDir = $archiveBaseDir . DIRECTORY_SEPARATOR . $patient->getId();
                
                if (!is_dir($patientDir) && !mkdir($patientDir, 0777, true) && !is_dir($patientDir)) {
                    throw new \RuntimeException("Impossible de créer le dossier d'archives pour le patient.");
                }

                foreach ($uploadedArchiveFiles as $uploadedFile) {
                    if (!$uploadedFile instanceof UploadedFile) {
                        continue;
                    } 
                    $allowedMime = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    if (!in_array($uploadedFile->getMimeType(), $allowedMime, true)) { 
                        continue;
                    } 

                    $originalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeName = preg_replace('/[^a-zA-Z0-9-_]/', '_', $originalName);
                    $uniqueName = time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeName . '.' . $uploadedFile->guessExtension();
                    $relativePath = '/uploads/patients/' . $patient->getId() . '/archive/' . $uniqueName;
                    $fullPath = $patientDir . DIRECTORY_SEPARATOR . $uniqueName;
                    
                    $uploadedFile->move($patientDir, $uniqueName);
                    $finalArchiveFiles[] = $relativePath;
                }
            } 
            
            $patient->setArchiveFiles($finalArchiveFiles);


            $this->em->persist($patient);
            $this->em->flush();
            $this->clearPatientsCache();
            $this->focusRealtimePublisher->publishPatientRefresh($patient, 'updated');

            return [
                'success' => true,
                'status' => 200,
                'patient' => $this->formatPatientSummary($patient),
                ...$this->formatPatientSummary($patient),
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => $e->getMessage(), 'status' => 400];
        } catch (\Exception $e) {
            return ['error' => 'Erreur : ' . $e->getMessage(), 'status' => 500];
        }
    }
 
public function addArchiveFile(int $patientId, string $name, UploadedFile $file, string $uploadDir): array
{
    $patient = $this->findActivePatient($patientId);
    if (!$patient) {
        return ['error' => 'Patient non trouvé', 'status' => 404];
    }

    $targetDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . 'patients' . DIRECTORY_SEPARATOR . 'archive';
    $patientDir = $targetDir . DIRECTORY_SEPARATOR . $patient->getId();

    if (!is_dir($patientDir) && !mkdir($patientDir, 0777, true) && !is_dir($patientDir)) {
        throw new \RuntimeException("Impossible de créer le dossier d'archives.");
    }

    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
    $safeName = preg_replace('/[^a-zA-Z0-9-_]/', '_', $originalName);
    $uniqueName = time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeName . '.' . $file->guessExtension();
    $relativePath = '/uploads/patients/' . $patient->getId() . '/archive/' . $uniqueName;
    $fullPath = $patientDir . DIRECTORY_SEPARATOR . $uniqueName;

    $file->move($patientDir, $uniqueName);
    
    $patient->addArchiveFile($name, $relativePath);
    $this->em->flush();
    $this->clearPatientsCache();

    return [
        'success' => true,
        'file' => ['nom' => $name, 'url' => $relativePath]
    ];
}

public function removeArchiveFile(int $patientId, string $fileUrl): array
{
    $patient = $this->findActivePatient($patientId);
    if (!$patient) {
        return ['error' => 'Patient non trouvé', 'status' => 404];
    }

    $archiveFiles = $patient->getArchiveFiles();
    $found = false;
    foreach ($archiveFiles as $file) {
        if ($file['url'] === $fileUrl) {
            $found = true;
            break;
        }
    }

    if (!$found) {
        return ['error' => 'Fichier non trouvé dans la liste', 'status' => 404];
    }

    // Suppression physique
    $basePath = dirname(__DIR__, 4) . '/public';
    $fullPath = $basePath . $fileUrl;
    if (file_exists($fullPath) && is_file($fullPath)) {
        unlink($fullPath);
    }

    $patient->removeArchiveFile($fileUrl);
    $this->em->flush();
    $this->clearPatientsCache();

    return ['success' => true, 'message' => 'Fichier supprimé'];
}

    public function checkConsultationActive(int $id): array
    {
        if (!$this->findActivePatient($id)) {
            return [
                'hasActive' => false,
                'consultationId' => null,
                'hasFiche' => false,
            ];
        }

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
            'hasFiche' => $consultation->getFicheMedicale() !== null || $consultation->getFicheMedicale() !== null,
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
            ->select('p.id, p.nom, p.prenom, p.telephone')
            ->andWhere('p.deletedAt IS NULL');

        $like = '%' . $term . '%';

        $orX = $qb->expr()->orX(
            'LOWER(p.nom) LIKE :term',
            'LOWER(p.prenom) LIKE :term',
            'LOWER(p.telephone) LIKE :term',
            "LOWER(CONCAT(p.nom, ' ', p.prenom)) LIKE :term",
            "LOWER(CONCAT(p.prenom, ' ', p.nom)) LIKE :term"
        );
 
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
        $patient = $this->findActivePatient($id);
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
            'photo' => $this->resolvePatientPhoto($patient),
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
            'referencement' => $patient->getReferencement() ?? '',
            'contactUrgence' => $contactUrgence,
            'smsPreferences' => $this->extractSmsPreferences($patient),
            'insuranceProfile' => $this->formatInsuranceProfile($patient),
            'derniereConsultation' => $derniereConsultation,
        ];
    }

    public function getPatientWithMedicalData(int $id): ?Patient
    {
        $patient = $this->patientRepo->findWithMedicalData($id);

        if (!$patient instanceof Patient || $patient->isDeleted()) {
            return null;
        }

        return $patient;
    }

    public function createRdv(array $data, ?User $actor = null): array
    {
        if (!isset($data['patient_id'], $data['medecin_id'], $data['date'], $data['time'])) {
            return ['error' => 'Missing required fields', 'status' => 400];
        }

        $patient = $this->findActivePatient((int) $data['patient_id']);
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

            $smsQueuedCount = 0;
            if (isset($data['smsReminder']) && is_array($data['smsReminder'])) {
                $smsQueuedCount = $this->queueAppointmentRemindersForRdv(
                    $rdv,
                    $data['smsReminder'],
                    (string) ($data['cabinet_name'] ?? 'ORODENT')
                );
            }

            return ['success' => true, 'status' => 201, 'rdv_id' => $rdv->getId(), 'smsQueuedCount' => $smsQueuedCount];
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
        $author = $actor?->getUsername() ?? 'le systÃ¨me';
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
        $patient = $this->findActivePatient($id);
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
        $patient = $this->findActivePatient($id);
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

        $this->ensurePatientPortalAccount($patient);
        $this->em->flush();

        return [
            'success' => true,
            'message' => 'Compte patient cree avec succes.',
            'account' => $this->formatPortalAccount($patient),
        ];
    }

    public function createMissingPatientPortalAccounts(): array
    {
        $patients = $this->patientRepo->createQueryBuilder('p')
            ->leftJoin('p.portalUser', 'portalUser')
            ->andWhere('portalUser.id IS NULL')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();

        $created = 0;

        foreach ($patients as $patient) {
            if (!$patient instanceof Patient) {
                continue;
            }

            $this->ensurePatientPortalAccount($patient);
            ++$created;
        }

        if ($created > 0) {
            $this->em->flush();
            $this->clearPatientsCache();
        }

        return [
            'success' => true,
            'createdCount' => $created,
            'message' => sprintf('%d compte(s) patient créé(s).', $created),
        ];
    }

    public function resetPatientPortalPassword(int $id): array
    {
        $patient = $this->findActivePatient($id);
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
        $patient = $this->findActivePatient($id);
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

    private function ensurePatientPortalAccount(Patient $patient): User
    {
        $user = $patient->getPortalUser();
        if ($user instanceof User) {
            return $user;
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

        return $user;
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
        $patient = $this->findActivePatient($id);
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
        $rdvSmsSummaries = $this->buildAppointmentSmsSummaryMap($patient);
        foreach ($rdvRepo->findBy(['patient' => $patient]) as $r) {
            $rdvs[] = [
                'id' => $r->getId(),
                'dateCreation' => $r->getDateCreation(),
                'dateRdv' => $r->getDateRdv()->format('Y-m-d H:i'),
                'salle' => $r->getSalle()?->getNom(),
                'medecinNom' => $r->getMedecin()->getFullName(),
                'statut' => $r->getStatut(),
                'smsReminder' => $rdvSmsSummaries[$r->getId()] ?? null,
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

        $factures = $this->cashdeskService->listFacturesImpayeesByPatient($patient->getId());
        $paiements = $this->cashdeskService->listPaiementsByPatients($patient);
        

        return [
            'id' => $patient->getId(),
            'nom' => $patient->getNom(),
            'prenom' => $patient->getPrenom(),
            'photo' => $this->resolvePatientPhoto($patient),
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
            'referencement' => $patient->getReferencement() ?? '',
            'contactUrgence' => $contactUrgence,
            'portalAccount' => $this->formatPortalAccount($patient),
            'smsPreferences' => $this->extractSmsPreferences($patient),
            'insuranceProfile' => $this->formatInsuranceProfile($patient),
            'allergies' => $allergies,
            'antecedents' => $antecedents,
            'derniereConsultation' => $derniereConsultation,
            'rdvs' => $rdvs,
            'fiches' => $fiches,
            'factures' => $factures,
            'paiements' => $paiements,
            'archiveFiles' => $patient->getArchiveFiles(),
        ];
    }

    public function updateDossier(int $id, array $payload): array
    {
        if (!$payload) {
            return ['error' => 'JSON invalide', 'status' => 400];
        }

        $patient = $this->findActivePatient($id);
        if (!$patient) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        }

        $simples = ['nom', 'prenom', 'sexe', 'telephone', 'adresse', 'numCarnet', 'groupeSanguin', 'email', 'profession', 'lieuNaissance', 'referencement'];
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
        try {
            $this->applyInsuranceProfile($patient, $payload);
        } catch (\InvalidArgumentException $e) {
            return ['error' => $e->getMessage(), 'status' => 400];
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
        $this->clearPatientsCache();

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

    private function formatInsuranceProfile(Patient $patient): ?array
    {
        $profile = $patient->getAssuranceProfile();
        if (!$profile) {
            return null;
        }

        $assurance = $profile->getAssurance();

        return [
            'id' => $profile->getId(),
            'enabled' => true,
            'coverageRate' => $profile->getCoverageRate(),
            'formData' => $profile->getFormData(),
            'assurance' => $assurance ? [
                'id' => $assurance->getId(),
                'code' => $assurance->getCode(),
                'nom' => $assurance->getNom(),
                'logoPath' => $assurance->getLogoPath(),
                'actif' => $assurance->isActif(),
            ] : null,
        ];
    }

    private function buildAppointmentSmsSummaryMap(Patient $patient): array
    {
        $patientId = $patient->getId();
        if (!$patientId) {
            return [];
        }

        $items = $this->smsQueueRepository->findAppointmentRemindersForPatients([$patientId]);
        $summaries = [];

        foreach ($items as $item) {
            if (!$item instanceof SmsQueue) {
                continue;
            }

            $metadata = $item->getMetadata() ?? [];
            $rdvId = (int) ($metadata['rdvId'] ?? 0);
            if ($rdvId <= 0 || isset($summaries[$rdvId])) {
                continue;
            }

            $status = $item->getStatus();
            $sendAt = $item->getSendAt();
            $sentAt = $item->getSentAt();
            $isScheduled = $status === SmsQueue::STATUS_PENDING && $sendAt instanceof DateTimeImmutable && $sendAt > new DateTimeImmutable();

            $label = match (true) {
                $isScheduled => 'Programmé',
                $status === SmsQueue::STATUS_SENT => 'Envoyé',
                $status === SmsQueue::STATUS_SENDING => 'Envoi en cours',
                $status === SmsQueue::STATUS_FAILED => 'Non envoyé',
                default => 'En attente',
            };

            $summaries[$rdvId] = [
                'queueId' => $item->getId(),
                'status' => $status,
                'label' => $label,
                'source' => $item->getSource(),
                'isAutomatic' => $item->getSource() === 'appointment-auto',
                'sendAt' => $sendAt?->format('Y-m-d H:i:s'),
                'sentAt' => $sentAt?->format('Y-m-d H:i:s'),
                'lastError' => $item->getLastError(),
                'message' => $item->getMessage(),
            ];
        }

        return $summaries;
    }

    private function applyInsuranceProfile(Patient $patient, array $data): void
    {
        $raw = $data['insuranceProfile'] ?? $data['assuranceProfile'] ?? null;
        if ($raw === null) {
            return;
        }

        if (!is_array($raw)) {
            throw new \InvalidArgumentException('Le profil assurance patient est invalide.');
        }

        $enabled = (bool) ($raw['enabled'] ?? $raw['active'] ?? true);
        $existing = $patient->getAssuranceProfile();

        if (!$enabled) {
            if ($existing) {
                $patient->setAssuranceProfile(null);
                $this->em->remove($existing);
            }

            return;
        }

        $assurance = null;
        $assuranceCode = trim((string) ($raw['assuranceCode'] ?? $raw['code'] ?? ''));
        if ($assuranceCode !== '') {
            $assurance = $this->assuranceRepo->findOneByCode($assuranceCode);
        }

        if (!$assurance) {
            $assuranceId = (int) ($raw['assuranceId'] ?? $raw['id'] ?? 0);
            if ($assuranceId > 0) {
                $assurance = $this->assuranceRepo->find($assuranceId);
            }
        }

        if (!$assurance || !$assurance->isActif()) {
            throw new \InvalidArgumentException('Assurance invalide ou inactive pour ce patient.');
        }

        $coverageRateRaw = $raw['coverageRate'] ?? $raw['tauxCouverture'] ?? null;
        $coverageRate = $coverageRateRaw === null || $coverageRateRaw === ''
            ? null
            : max(0.0, min(100.0, (float) $coverageRateRaw));

        $formData = is_array($raw['formData'] ?? null) ? $raw['formData'] : [];

        $profile = $existing ?? new PatientAssuranceProfile();
        $profile
            ->setPatient($patient)
            ->setAssurance($assurance)
            ->setCoverageRate($coverageRate)
            ->setFormData($formData);

        $patient->setAssuranceProfile($profile);
        $this->em->persist($profile);
    }

    public function addAntecedent(int $patientId, array $payload): array
    {
        $patient = $this->findActivePatient($patientId);
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
        $patient = $this->findActivePatient($patientId);
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
        $patient = $this->findActivePatient($patientId);
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
        $patient = $this->findActivePatient($patientId);
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
        return $this->findActivePatient($id);
    }

    public function getPrintInfosPersoData(int $id): ?array
    {
        $patient = $this->findActivePatient($id);
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
        $patient = $this->findActivePatient($patientId);
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

