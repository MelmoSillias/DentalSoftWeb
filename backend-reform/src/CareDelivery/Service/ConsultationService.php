<?php

namespace App\CareDelivery\Service;

use App\Billing\Entity\Assurance;
use App\Dto\Focus\FocusReceptionBillingDto;
use App\Dto\Focus\FocusReceptionConsultationDto;
use App\Dto\Focus\FocusReceptionInvoiceLineDto;
use App\Dto\Focus\FocusReceptionPatientDto;
use App\Dto\Focus\FocusReceptionPayloadDto;
use App\Dto\Focus\FocusReceptionPaymentDto;
use App\Billing\Entity\Facture;
use App\Billing\Entity\FactureAssurance;
use App\Billing\Entity\Paiement;
use App\Billing\Entity\Transaction;
use App\Billing\Repository\DevisRepository;
use App\Billing\Repository\FactureRepository;
use App\CareDelivery\Entity\ActeMedical;
use App\CareDelivery\Entity\Consultation;
use App\CareDelivery\Entity\Ordonnance;
use App\CareDelivery\Entity\OrdonnanceLigne;
use App\CareDelivery\Repository\ConsultationRepository; 
use App\ClinicalRecord\Entity\FicheMedicale;
use App\ClinicalRecord\Repository\FicheMedicaleRepository;
use App\Communication\Service\NotificationLinkBuilder;
use App\Communication\Service\NotificationRecipientResolver;
use App\Shared\Event\EntityActionEvent;
use App\Focus\Service\FocusRealtimePublisher;
use App\IdentityAccess\Entity\Employe;
use App\IdentityAccess\Entity\User;
use App\IdentityAccess\Repository\EmployeRepository;
use App\Patient\Entity\Allergy;
use App\Patient\Entity\Antecedent;
use App\Patient\Entity\Patient;
use App\Settings\Service\GlobalSettingsService;
use App\Billing\Entity\ModeDePaiement;
use App\Patient\Repository\PatientRepository;
use App\Scheduling\Entity\Salle;
use App\Scheduling\Repository\SalleRepository;
use App\CareDelivery\Service\ConsultationNotificationService;
use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface; 
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ConsultationService
{
    private string $projectDir;

    public function __construct(
        private EntityManagerInterface $em,
        private DevisRepository $devisRepo,
        private ConsultationRepository $consultationRepo,
        private EmployeRepository $employeRepo,
        private SalleRepository $salleRepo,
        private FocusRealtimePublisher $focusRealtimePublisher,
        private NotificationRecipientResolver $notificationRecipientResolver,
        private EventDispatcherInterface $eventDispatcher,
        private UserPasswordHasherInterface $passwordHasher,
        ParameterBagInterface $params,
        private CacheInterface $cache,
        private GlobalSettingsService $globalSettingsService,
        private PatientRepository $patientRepo,
        private ConsultationNotificationService $consultationNotificationService,
        private FicheMedicaleRepository $ficheMedicaleRepo,
        private FactureRepository $factureRepo,
        private ActAttributionResolver $actAttributionResolver,
    ) {
        $this->projectDir = $params->get('kernel.project_dir');
    }

    public function createConsultation(array $data, ?User $triggeredBy = null): array
    {
        try {
            $isMedecinRequired = $this->globalSettingsService->isMedecinRequiredOnConsultationCreation();
            if ($isMedecinRequired && empty($data['medecin_id'])) {
                return [
                    'error' => 'Le medecin est requis pour creer la consultation.',
                    'status' => 400,
                ];
            }

            $result = $this->em->wrapInTransaction(function () use ($data, $triggeredBy): array {
                $patient = $this->patientRepo->find($data['patient_id'] ?? null);
                if (!$patient) {
                    return [
                        'error' => 'Patient introuvable.',
                        'status' => 404,
                    ];
                }

                $isPayant = ((int) ($data['payant'] ?? 0)) === 1;
                $insuranceEnabled = $this->patientHasActiveInsurance($patient);
                $profile = $patient->getAssuranceProfile();
                $assurance = $profile?->getAssurance();
                $insuranceRate = $insuranceEnabled
                    ? max(0, min(100, (float) ($profile?->getCoverageRate() ?? 0)))
                    : 0.0;

                $defaultConsultationAmount = $this->globalSettingsService->getConsultationPrice();
                $consultationAmount = 0.0;
                if ($isPayant) {
                    $canEditPrice = $this->globalSettingsService->isConsultationPriceEditableOnCreation();
                    $consultationAmount = $canEditPrice
                        ? (float) ($data['consultation_amount'] ?? $defaultConsultationAmount)
                        : $defaultConsultationAmount;
                    if ($consultationAmount <= 0) {
                        $consultationAmount = $defaultConsultationAmount;
                    }
                }

                $insuranceAmount = $insuranceEnabled && $isPayant
                    ? ($consultationAmount * $insuranceRate) / 100
                    : 0.0;
                $patientAmount = $isPayant ? max(0.0, $consultationAmount - $insuranceAmount) : 0.0;

                if ($patientAmount > 0 && !$insuranceEnabled && !isset($data['mode_paiement_id'])) {
                    return [
                        'error' => 'Le mode de paiement est requis pour une consultation payante.',
                        'status' => 400,
                    ];
                }

                $consultation = $this->consultationRepo->NewConsultation($data, $this->patientRepo, $this->employeRepo, false);
                $createdPaiementId = null;
                $patientPayment = null;
                $timestamp = new DateTimeImmutable();

                if ($patientAmount > 0 && !$insuranceEnabled) {
                    $modePaiement = $this->em->getRepository(ModeDePaiement::class)->find($data['mode_paiement_id']);

                    if (!$modePaiement) {
                        return [
                            'error' => 'Mode de paiement invalide.',
                            'status' => 400,
                        ];
                    }

                    $paiement = new Paiement();
                    $paiement->setFacture(null);
                    $paiement->setMode($modePaiement);
                    $paiement->setMontant($patientAmount);
                    $paiement->setDate($timestamp);
                    $consultation->setPaiement($paiement);
                    $this->em->persist($paiement);
                    $patientPayment = $paiement;

                    $transaction = new Transaction();
                    $transaction->setType('Revenue');
                    $transaction->setMontant((string) $patientAmount);
                    $transaction->setDateTransaction($timestamp);
                    $transaction->setDescription('Ticket de consultation #' . $consultation->getId() . ' | Part patient');
                    $transaction->setModeDePaiement($modePaiement);
                    $transaction->setConsultation($consultation);
                    $transaction->markValidated($timestamp);
                    $transaction->setPaiement($paiement);
                    $this->em->persist($transaction);
                }

                if ($insuranceEnabled && $assurance instanceof Assurance) {
                    $this->attachFactureAssurance(
                        $consultation,
                        $patient,
                        $assurance,
                        $insuranceRate,
                        $isPayant ? $consultationAmount : 0.0,
                        $isPayant,
                        $profile?->getFormData() ?? [],
                        'pending',
                    );
                }

                $this->em->flush();

                if ($patientPayment instanceof Paiement && $patientPayment->getId() !== null) {
                    $createdPaiementId = $patientPayment->getId();
                } elseif ($consultation->getPaiement()) {
                    $createdPaiementId = $consultation->getPaiement()->getId();
                }

                $this->consultationNotificationService->notifyCreation($consultation, $triggeredBy);

                return [
                    'success' => true,
                    'status' => 200,
                    'consultation_id' => $consultation->getId(),
                    'paiement_id' => $createdPaiementId,
                ];
            });

            return $result;
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'status' => 400,
            ];
        }
    }

    public function getMedecinForUser(?object $user): ?Employe
    {
        if (!$user) {
            return null;
        }

        return $this->employeRepo->findOneBy(['user' => $user]);
    }

    private function isMedecinUser(?object $user): bool
    {
        return $user instanceof User && in_array('ROLE_MEDECIN', $user->getRoles(), true);
    }

    private function enforceMedecinOwnership(Consultation $consultation, ?object $user, bool $restrictToMedecin): ?Employe
    {
        if (!$restrictToMedecin || !$this->isMedecinUser($user)) {
            return null;
        }

        $actorMedecin = $this->getMedecinForUser($user);
        if (!$actorMedecin) {
            throw new ConflictHttpException('Aucun médecin lié Ã  ce compte.');
        }

        $currentMedecin = $consultation->getMedecin();
        if ($currentMedecin && $currentMedecin->getId() !== $actorMedecin->getId()) {
            throw new ConflictHttpException('Cette consultation est déjÃ  prise en charge par un autre médecin.');
        }

        if (!$currentMedecin) {
            $consultation->setMedecin($actorMedecin);
            $this->em->persist($consultation);
            $this->em->flush();
            $this->focusRealtimePublisher->publishConsultationRefresh($consultation, 'assigned');
        }

        return $actorMedecin;
    }

    public function verifyConsultationMedecinPassword(int $consultationId, string $plainPassword): bool
    {
        $consultation = $this->consultationRepo->find($consultationId);
        if (!$consultation) {
            throw new NotFoundHttpException('Consultation introuvable');
        }

        $medecinUser = $consultation->getMedecin()?->getUser();
        if (!$medecinUser || trim($plainPassword) === '') {
            return false;
        }

        return $this->passwordHasher->isPasswordValid($medecinUser, $plainPassword);
    }

    private function ensureConsultationOpen(Consultation $consultation): void
    {
        if ($consultation->getStatut() === 1) {
            throw new ConflictHttpException('Cette consultation est déjÃ  clÃ´turée.');
        }
    }

    private function normalizeDentValue(mixed $value): string
    {
        if (is_array($value)) {
            $values = array_values(array_unique(array_filter(array_map(static fn($item) => trim((string) $item), $value))));
            return implode(',', $values);
        }

        if (is_string($value)) {
            $values = array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
            return implode(',', $values);
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return '';
    }

    private function normalizeDentList(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
    }

    private function applyConsultationPayload(Consultation $consultation, array $data, ?Employe $actorMedecin = null): void
    {
        $medecinId = $actorMedecin?->getId() ?? ($data['medecinId'] ?? $consultation->getMedecin()?->getId());
        if (!$medecinId) {
            throw new \InvalidArgumentException('Le médecin est obligatoire pour enregistrer la consultation.');
        }

        $infirmierId = $data['infirmierId'] ?? ($data['infirmierIds'][0] ?? null);
        $salleId = $data['salleId'] ?? null;

        $consultation->setMedecin($this->em->getReference(Employe::class, (int) $medecinId));
        $consultation->setInfirmier($infirmierId ? $this->em->getReference(Employe::class, (int) $infirmierId) : null);
        $consultation->setSalle($salleId ? $this->em->getReference(Salle::class, (int) $salleId) : null);
        $consultation->setType($data['type'] ?? $consultation->getType());
        $consultation->setNoteSeance($data['noteSeance'] ?? $consultation->getNoteSeance() ?? '');

        if (isset($data['actes']) && is_array($data['actes'])) {
            foreach ($consultation->getActes()->toArray() as $a) {
                $consultation->removeActe($a);
                $this->em->remove($a);
            }

            foreach ($data['actes'] as $a) {
                $act = new ActeMedical();
                $act->setDent($this->normalizeDentValue($a['dent'] ?? ($a['dents'] ?? '')))
                    ->setType($a['type'] ?? ($a['designation'] ?? ''))
                    ->setDescription($a['description'] ?? ($a['designation'] ?? ''))
                    ->setPrix((float) ($a['prix'] ?? $a['montant'] ?? 0))
                    ->setQuantite((int) ($a['quantite'] ?? $a['qte'] ?? 1))
                    ->setAttribution($this->actAttributionResolver->resolveFromPayload(is_array($a) ? $a : []));
                $consultation->addActe($act);
                $this->em->persist($act);
            }
        }

        $ordonnancePayload = null;
        if (isset($data['ordonnance']) && is_array($data['ordonnance'])) {
            $ordonnancePayload = $data['ordonnance'];
        } elseif (!empty($data['ordonnances']) && is_array($data['ordonnances'])) {
            $first = $data['ordonnances'][0] ?? null;
            $ordonnancePayload = is_array($first) ? $first : null;
        }

        if (is_array($ordonnancePayload)) {
            $this->createOrdonnanceEntityFromPayload($consultation, $ordonnancePayload);
        }
    }

    private function findLastFicheMedicale(?Patient $patient): ?FicheMedicale
    {
        return $this->ficheMedicaleRepo->findLatestByPatient($patient);
    }

    /**
     * @return array{0: FicheMedicale, 1: bool}
     */
    private function resolveFicheForConsultation(
        Consultation $consultation,
        ?int $ficheId = null,
        bool $forceCreate = false,
        bool $allowDuplicate = false,
    ): array {
        $patient = $consultation->getPatient();
        if (!$patient) {
            throw new \InvalidArgumentException('Consultation sans patient.');
        }

        $existingFiche = $consultation->getFicheMedicale();

        // Explicit duplicate creation (dossier patient only).
        if ($forceCreate && $allowDuplicate) {
            $fiche = new FicheMedicale();
            $fiche->setPatient($patient);
            $this->em->persist($fiche);
            $consultation->setFicheMedicale($fiche);

            return [$fiche, true];
        }

        // Already linked and not forcing a switch: reuse.
        if (!$forceCreate && $existingFiche) {
            if ($ficheId === null || $ficheId === $existingFiche->getId()) {
                return [$existingFiche, false];
            }
        }

        // Explicit fiche id provided: link it (must belong to patient).
        if ($ficheId) {
            $ficheMedicale = $this->ficheMedicaleRepo->find($ficheId);
            if (!$ficheMedicale) {
                throw new NotFoundHttpException('Fiche introuvable');
            }

            if ($ficheMedicale->getPatient()?->getId() !== $patient->getId()) {
                throw new \InvalidArgumentException('La fiche ne correspond pas au patient de la consultation.');
            }

            $consultation->setFicheMedicale($ficheMedicale);

            return [$ficheMedicale, false];
        }

        // Default path: always continue latest fiche (or create if none).
        $lastFiche = $this->findLastFicheMedicale($patient);
        if ($lastFiche) {
            $consultation->setFicheMedicale($lastFiche);

            return [$lastFiche, false];
        }

        $fiche = new FicheMedicale();
        $fiche->setPatient($patient);
        $this->em->persist($fiche);
        $consultation->setFicheMedicale($fiche);

        return [$fiche, true];
    }

    private function resolvePendingFicheData(Consultation $consultation): array
    {
        $patient = $consultation->getPatient();
        $lastFicheMedicale = $this->findLastFicheMedicale($patient);

        $ficheMedicale = $consultation->getFicheMedicale();

        $linkedFiche = $ficheMedicale;

        $lastFicheCandidate = null;
        if (!$linkedFiche) {
            $lastFicheCandidate = $lastFicheMedicale;
        }

        return [
            'ficheMedicale' => $ficheMedicale,
            'fiche' => $linkedFiche,
            'ficheId' => $linkedFiche?->getId(),
            'hasFiche' => (bool) ($linkedFiche || $lastFicheCandidate),
            'lastFicheId' => $lastFicheCandidate?->getId(),
            'motif' => $lastFicheMedicale?->getEntretien()?->getMotifConsultation() ?? '',
        ];
    }

    private function buildPendingConsultationsData(array $consultations): array
    {
        return array_map(function (Consultation $c) {
            $patient = $c->getPatient();
            $ficheData = $this->resolvePendingFicheData($c);

            return [
                'id' => $c->getId(),
                'patient' => $patient->getNom() . ' ' . $patient->getPrenom(),
                'medecin' => $c->getMedecin() ? $c->getMedecin()->getFullName() : null,
                'dateDebut' => $c->getCreatedAt()->format('Y-m-d H:i'),
                'hasFiche' => $ficheData['hasFiche'],
                'fiche' => $ficheData['fiche'],
                'ficheId' => $ficheData['ficheId'],
                'lastFicheId' => $ficheData['lastFicheId'],
                'hasInsurance' => $c->getFactureAssurance() !== null || $this->patientHasActiveInsurance($patient),
                'assuranceNom' => $c->getFactureAssurance()?->getAssurance()?->getNom()
                    ?? $patient->getAssuranceProfile()?->getAssurance()?->getNom(),
            ];
        }, $consultations);
    }

    private function resolveDayBounds(?string $dateStr): array
    {
        $date = $dateStr ? (\DateTime::createFromFormat('Y-m-d', $dateStr) ?: new \DateTime()) : new \DateTime();

        return [
            (clone $date)->setTime(0, 0, 0),
            (clone $date)->setTime(23, 59, 59),
        ];
    }

    private function getConsultationsForDay(?string $dateStr, $user): array
    {
        [$start, $end] = $this->resolveDayBounds($dateStr);

        $qb = $this->em->createQueryBuilder()
            ->select('c', 'p', 'm', 'f', 'pa', 'fa', 'ap', 'ass')
            ->from(Consultation::class, 'c')
            ->join('c.patient', 'p')
            ->leftJoin('c.medecin', 'm')
            ->leftJoin('c.facture', 'f')
            ->leftJoin('c.paiement', 'pa')
            ->leftJoin('c.factureAssurance', 'fa')
            ->leftJoin('p.assuranceProfile', 'ap')
            ->leftJoin('ap.assurance', 'ass')
            ->where('c.CreatedAt BETWEEN :start AND :end')
            ->orderBy('c.CreatedAt', 'ASC')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($user instanceof User && in_array('ROLE_MEDECIN', $user->getRoles(), true)) {
            $medecin = $this->employeRepo->FindOneBy(['user' => $user]);
            if ($medecin) {
                $qb->andWhere('(m = :medecin OR m IS NULL)')
                    ->setParameter('medecin', $medecin);
            }
        }

        return $qb->getQuery()->getResult();
    }

    private function resolveFocusFactState(?Facture $facture): ?int
    {
        if (!$facture) {
            return null;
        }

        if ($facture->isReglee() || ($facture->getRestePatient() <= 0.0 && $facture->getMontantTotal() > 0.0)) {
            return 1;
        }

        return 0;
    }

    public function formatPatientConsultationSummary(Consultation $consultation): array
    {
        $facture = $consultation->getFacture();
        $statut = $consultation->getStatut();

        $actes = [];
        foreach ($consultation->getActes() as $acte) {
            $quantite = max(1, (int) ($acte->getQuantite() ?? 1));
            $prix = (float) ($acte->getPrix() ?? 0);
            $actes[] = [
                'id' => $acte->getId(),
                'type' => $acte->getType(),
                'description' => $acte->getDescription(),
                'dent' => $acte->getDent(),
                'prix' => $prix,
                'quantite' => $quantite,
                'montant' => $prix * $quantite,
                'attribution' => $acte->getAttribution(),
            ];
        }

        return [
            'id' => $consultation->getId(),
            'date' => $consultation->getCreatedAt()?->format('Y-m-d H:i'),
            'statut' => $statut,
            'state' => $statut,
            'medecin' => $consultation->getMedecin()?->getFullName(),
            'factureMontant' => $facture?->getMontantTotal(),
            'factureStatut' => $facture?->isReglee() ? 1 : 0,
            'factureId' => $facture?->getId(),
            'factModifiable' => $statut === 1 && $this->isFactureModifiable($facture),
            'actes' => $actes,
        ];
    }

    private function isFactureModifiable(?Facture $facture): bool
    {
        if (!$facture) {
            return false;
        }

        if ($facture->getPaiements()->count() > 0) {
            return false;
        }

        $consultation = $facture->getConsultation();
        $factureAssurance = $consultation?->getFactureAssurance();
        if ($factureAssurance === null) {
            return true;
        }

        $lot = $factureAssurance->getLotFactureAssurance();
        if ($lot !== null) {
            $statut = $lot->getStatut() === 'recouvre' ? 'rembourse' : $lot->getStatut();
            if (in_array($statut, ['envoye', 'confirme', 'partiellement_rembourse', 'rembourse'], true)) {
                return false;
            }
        }

        return $factureAssurance->computePatientPaidAmount() <= 0;
    }

    private function resolveFactureDateFromConsultation(Consultation $consultation): \DateTime
    {
        $createdAt = $consultation->getCreatedAt();

        return $createdAt instanceof \DateTimeInterface
            ? \DateTime::createFromInterface($createdAt)
            : new \DateTime('now');
    }

    private function parseFactureDateInput(?string $date, ?string $time, Consultation $consultation): \DateTime
    {
        $date = trim((string) ($date ?? ''));
        $time = trim((string) ($time ?? ''));

        if ($date !== '' && $time !== '') {
            return new \DateTime($date . ' ' . $time);
        }

        if ($date !== '') {
            return new \DateTime($date);
        }

        return $this->resolveFactureDateFromConsultation($consultation);
    }

    private function buildFocusConsultationDto(Consultation $consultation, int $patientImpayees = 0): FocusReceptionConsultationDto
    {
        $ficheData = $this->resolvePendingFicheData($consultation);
        $patient = $consultation->getPatient();

        return new FocusReceptionConsultationDto([
            'id' => $consultation->getId(),
            'numero' => $consultation->getNumeroPassage(),
            'patient' => [
                'id' => $patient->getId(),
                'nom' => $patient->getNom(),
                'prenom' => $patient->getPrenom(),
                'telephone' => $patient->getTelephone(),
                'photo' => $patient->getPhoto(),
                'createdAt' => $patient->getDateInscription()?->format(DATE_ATOM),
                'insuranceProfile' => $this->resolvePatientInsuranceProfile($patient),
                'impayees' => $patientImpayees,
            ],
            'patientName' => $patient->getFullName(),
            'patientPhoto' => $patient->getPhoto(),
            'patientId' => $patient->getId(),
            'patientCreatedAt' => $patient->getDateInscription()?->format(DATE_ATOM),
            'patientImpayees' => $patientImpayees,
            'medecin' => $consultation->getMedecin()?->getFullName(),
            'isPaid' => $consultation->getPaiement() ? true : false,
            'paiementId' => $consultation->getPaiement()?->getId(),
            'paiementAmount' => $consultation->getPaiement()?->getMontant() ?? 0,
            'createdAt' => $consultation->getCreatedAt()?->format(DATE_ATOM),
            'motif' => $ficheData['motif'],
            'factstate' => $this->resolveFocusFactState($consultation->getFacture()),
            'state' => $consultation->getStatut(),
            'hasFiche' => $ficheData['hasFiche'],
            'ficheId' => $ficheData['ficheId'],
            'lastFicheId' => $ficheData['lastFicheId'],
            'hasInsurance' => $consultation->getFactureAssurance() !== null || $this->patientHasActiveInsurance($patient),
            'assuranceNom' => $consultation->getFactureAssurance()?->getAssurance()?->getNom()
                ?? $patient->getAssuranceProfile()?->getAssurance()?->getNom(),
        ]);
    }

    private function patientHasActiveInsurance(?\App\Patient\Entity\Patient $patient): bool
    {
        $profile = $patient?->getAssuranceProfile();
        $assurance = $profile?->getAssurance();

        return $profile !== null && $assurance !== null && $assurance->isActif();
    }

    private function resolvePatientInsuranceProfile(?\App\Patient\Entity\Patient $patient): ?array
    {
        if (!$this->patientHasActiveInsurance($patient)) {
            return null;
        }
        $assurance = $patient->getAssuranceProfile()?->getAssurance();

        return [
            'enabled' => true,
            'coverageRate' => $patient->getAssuranceProfile()?->getCoverageRate(),
            'assurance' => [
                'id' => $assurance?->getId(),
                'nom' => $assurance?->getNom(),
                'code' => $assurance?->getCode(),
            ],
        ];
    }

    /**
     * Creates FactureAssurance when the patient is insured and none exists yet
     * (e.g. consultation créée via RDV, ou créée avant le profil assurance).
     */
    private function ensureFactureAssurance(Consultation $consultation): ?FactureAssurance
    {
        $existing = $consultation->getFactureAssurance();
        if ($existing !== null) {
            return $existing;
        }

        $patient = $consultation->getPatient();
        if (!$this->patientHasActiveInsurance($patient)) {
            return null;
        }

        $profile = $patient->getAssuranceProfile();
        $assurance = $profile?->getAssurance();
        if (!$assurance instanceof Assurance) {
            return null;
        }

        // Ticket déjà encaissé hors assurance : ne pas re-facturer la consultation dans la FA.
        // Les actes restent couverts via buildActeLignes().
        return $this->attachFactureAssurance(
            $consultation,
            $patient,
            $assurance,
            max(0, min(100, (float) ($profile?->getCoverageRate() ?? 0))),
            0.0,
            false,
            $profile?->getFormData() ?? [],
            'pending',
        );
    }

    private function attachFactureAssurance(
        Consultation $consultation,
        Patient $patient,
        Assurance $assurance,
        float $insuranceRate,
        float $consultationAmount,
        bool $isConsultationPayante,
        array $formData,
        string $insuranceStatus = 'pending',
    ): FactureAssurance {
        $factureAssurance = new FactureAssurance();
        $factureAssurance
            ->setConsultation($consultation)
            ->setPatient($patient)
            ->setAssurance($assurance)
            ->setCoverageRate($insuranceRate > 0 ? $insuranceRate : null)
            ->setDateFacture(new DateTime())
            ->setConsultationAmount($isConsultationPayante ? $consultationAmount : 0.0)
            ->setIsConsultationPayante($isConsultationPayante)
            ->setInsuranceStatus($insuranceStatus)
            ->setAssuranceSnapshot([
                'code' => $assurance->getCode(),
                'nom' => $assurance->getNom(),
                'logoPath' => $assurance->getLogoPath(),
                'website' => $assurance->getWebsite(),
                'email' => $assurance->getEmail(),
                'formData' => $formData,
            ]);

        $consultation->setFactureAssurance($factureAssurance);
        $this->em->persist($factureAssurance);

        return $factureAssurance;
    }

    private function buildFocusPatientDto(Patient $patient, int $impayees = 0): FocusReceptionPatientDto
    {
        return new FocusReceptionPatientDto(
            $patient->getId(),
            $patient->getNom() ?? '',
            $patient->getPrenom() ?? '',
            $patient->getFullName(),
            $patient->getTelephone(),
            $patient->getDateInscription()?->format(DATE_ATOM),
            $impayees,
        );
    }

    private function buildFocusBillingDto(Facture $facture): FocusReceptionBillingDto
    {
        $consultation = $facture->getConsultation();
        $runningTotal = 0.0;
        $lineIndex = 1;

        $lines = array_map(function (ActeMedical $acte) use (&$runningTotal, &$lineIndex): FocusReceptionInvoiceLineDto {
            $quantity = max(1, (int) ($acte->getQuantite() ?? 1));
            $unitPrice = (float) ($acte->getPrix() ?? 0);
            $lineTotal = $quantity * $unitPrice;
            $runningTotal += $lineTotal;

            return new FocusReceptionInvoiceLineDto(
                $acte->getId() ?? $lineIndex++,
                $acte->getType() ?: ($acte->getDescription() ?: 'Soin'),
                $quantity,
                $unitPrice,
                $lineTotal,
            );
        }, $consultation?->getActes()->toArray() ?? []);

        $payments = $facture->getPaiements()->toArray();
       
        $paymentDtos = array_map(
            static fn (Paiement $payment) => new FocusReceptionPaymentDto(
                $payment->getId() ?? 0,
                $payment->getMontant(),
                $payment->getMode()?->getLibelle(),
                $payment->getDate()?->format(DATE_ATOM),
                method_exists($payment, 'getiement') ? ((string) ($payment->getiement() ?? 'direct')) : 'direct',
                'paiement',
                $payment->getTransaction()?->getValidationStatus() ?? 'validated',
            ),
            $payments
        );

        $montants = $facture->computeMontantsFromConsultation();
        $total = (float) $montants['montantTotal'];
        $remaining = (float) $montants['restePatient'];

        return new FocusReceptionBillingDto(
            $facture->getId() ?? 0,
            $total,
            $remaining,
            [
                'label' => $remaining <= 0 && $total > 0
                    ? 'Facture reglee'
                    : ($total <= 0
                        ? ($facture->isReglee() ? 'Facture vide validee' : 'Facture vide')
                        : 'Facture ouverte'),
                'severity' => $remaining <= 0 && $total > 0
                    ? 'success'
                    : ($total <= 0
                        ? ($facture->isReglee() ? 'success' : 'contrast')
                        : 'warn'),
            ],
            $lines,
            $paymentDtos,
        );
    }

    public function getReceptionFocusData(?string $dateStr, $user): FocusReceptionPayloadDto
    {
        [$start, $end] = $this->resolveDayBounds($dateStr);
        $consultations = $this->getConsultationsForDay($dateStr, $user);

        $recentPatients = $this->em->getRepository(Patient::class)->createQueryBuilder('p')
            ->where('p.dateInscription BETWEEN :start AND :end')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.dateInscription', 'DESC')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        $patientIds = [];
        foreach ($consultations as $consultation) {
            $patientId = $consultation->getPatient()?->getId();
            if ($patientId) {
                $patientIds[] = (int) $patientId;
            }
        }
        foreach ($recentPatients as $patient) {
            $patientIds[] = (int) $patient->getId();
        }

        $impayeesByPatientId = $this->factureRepo->sumUnpaidResteByPatientIds($patientIds);
        $unpaidByPatientId = $this->factureRepo->listUnpaidSummariesByPatientIds($patientIds);

        $consultationDtos = [];
        $billingByConsultation = [];

        foreach ($consultations as $consultation) {
            $patientId = (int) ($consultation->getPatient()?->getId() ?? 0);
            $consultationDtos[] = $this->buildFocusConsultationDto(
                $consultation,
                (int) ($impayeesByPatientId[$patientId] ?? 0),
            );
            if ($consultation->getFacture()) {
                $billingByConsultation[(string) $consultation->getId()] = $this->buildFocusBillingDto($consultation->getFacture());
            }
        }

        $patientDtos = array_map(
            fn (Patient $patient) => $this->buildFocusPatientDto(
                $patient,
                (int) ($impayeesByPatientId[(int) $patient->getId()] ?? 0),
            ),
            $recentPatients,
        );

        return new FocusReceptionPayloadDto($consultationDtos, $patientDtos, $billingByConsultation, $unpaidByPatientId);
    }

    public function getPendingConsultationsContextForUser(?object $user, bool $restrictToMedecin): array
    {
        $consultations = $this->consultationRepo->findPendingConsultations();

        if ($restrictToMedecin) {
            $medecin = $this->getMedecinForUser($user);
            if ($medecin) {
                $consultations = array_filter($consultations, fn(Consultation $c) => $c->getMedecin() && $c->getMedecin()->getId() === $medecin->getId());
            }
        }

        $data = $this->buildPendingConsultationsData($consultations);

        return [
            'consultations' => $consultations,
            'data' => $data,
        ];
    }

    public function getConsultationDetailsData(int $id): array
    {
        $c = $this->consultationRepo->find($id);
        if (!$c) {
            throw new NotFoundHttpException("Consultation {$id} introuvable");
        }

        $actesData = [];
        foreach ($c->getActes() as $a) {
            $dentValue = (string) ($a->getDent() ?? '');
            $actesData[] = [
                'dent' => $dentValue,
                'dents' => $this->normalizeDentList($dentValue),
                'type' => $a->getType(),
                'description' => $a->getDescription(),
                'prix' => $a->getPrix(),
                'quantite' => $a->getQuantite(),
                'attribution' => $a->getAttribution(),
            ];
        }

        return [
            'entity' => $c,
            'data' => [
                'id' => $c->getId(),
                'date' => $c->getCreatedAt()->format('Y-m-d H:i'),
                'patient' => $c->getPatient()->getNom() . ' ' . $c->getPatient()->getPrenom(),
                'medecin' => $c->getMedecin()?->getFullName(),
                'medecinId' => $c->getMedecin()?->getId(),
                'infirmier' => $c->getInfirmier()?->getNom(),
                'salle' => $c->getSalle()?->getNom(),
                'type' => $c->getType(),
                'noteSeance' => $c->getNoteSeance(),
                'actes' => $actesData,
            ],
        ];
    }

    public function getFicheById(int $ficheId):FicheMedicale
    {
        $ficheMed = $this->em->getRepository(FicheMedicale::class)->find($ficheId);
        if ($ficheMed) {
            return $ficheMed;
        }

        throw new NotFoundHttpException("Fiche {$ficheId} introuvable");
    }

    public function getFicheAndConsultation(int $ficheId, int $consultationId, ?object $user = null, bool $restrictToMedecin = false): array
    {
        $consultation = $this->em->getRepository(Consultation::class)->find($consultationId);
        if (!$consultation) {
            throw new NotFoundHttpException("Consultation {$consultationId} introuvable");
        }

        $this->enforceMedecinOwnership($consultation, $user, $restrictToMedecin);

        $this->ensureConsultationOpen($consultation);

        $patientId = $consultation->getPatient()?->getId();

        $attachIfAllowed = function (FicheMedicale $fiche) use ($consultation, $patientId): bool {
            if ($fiche->getPatient()?->getId() !== $patientId) {
                return false;
            }

            if ($consultation->getFicheMedicale()) {
                return false;
            }

            if ($fiche instanceof FicheMedicale) {
                $consultation->setFicheMedicale($fiche);
            }

            $this->em->persist($consultation);
            $this->em->flush();

            return true;
        };

        $ficheMed = $this->em->getRepository(FicheMedicale::class)->find($ficheId);
        if ($ficheMed && ($consultation->getFicheMedicale() === $ficheMed || $attachIfAllowed($ficheMed))) {
            return [$ficheMed, $consultation];
        }

        throw new NotFoundHttpException("Consultation {$consultationId} introuvable pour la fiche {$ficheId}");
    }

    public function getConsultationJson(int $ficheId, int $consultationId): array
    {
        [$fiche, $consultation] = $this->getFicheAndConsultation($ficheId, $consultationId);

        $patient = $fiche->getPatient();

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

        $patientData = [
            'id'        => $patient->getId(),
            'nom'       => $patient->getNom(),
            'prenom'    => $patient->getPrenom(),
            'age' => $patient->getDateNaissance() ? $patient->getDateNaissance()->format('Y-m-d') : null,
            'sexe'      => $patient->getSexe(),
            'telephone' => $patient->getTelephone(),
            'allergies' => $allergies,
            'antecedents' => $antecedents,
        ];

        $consultationData = [
            'id'         => $consultation->getId(),
            'type'       => $consultation->getType(),
            'noteSeance' => $consultation->getNoteSeance(),
            'medecin'    => $consultation->getMedecin()    ? ['id' => $consultation->getMedecin()->getId(),   'name' => $consultation->getMedecin()->getFullName()] : null,
            'infirmier'  => $consultation->getInfirmier()  ? ['id' => $consultation->getInfirmier()->getId(), 'name' => $consultation->getInfirmier()->getFullName()] : null,
            'salle'      => $consultation->getSalle()      ? ['id' => $consultation->getSalle()->getId(),     'name' => $consultation->getSalle()->getNom()]         : null,
        ];

        $ficheData = [
            'id'                     => $fiche->getId(),
            'motif'                  => $fiche->getMotif(),
            'histoireMaladie'        => $fiche->getHistoireMaladie(),
            'soinsAnterieurs'        => $fiche->getSoinsAnterieurs(),
            'exoInspection'          => $fiche->getExoInspection(),
            'exoPalpation'           => $fiche->getExoPalpation(),
            'endoInspection'         => $fiche->getEndoInspection(),
            'endoPalpation'          => $fiche->getEndoPalpation(),
            'occlusion'              => $fiche->getOcclusion(),
            'examenParodontal'       => $fiche->getExamenParodontal(),
            'diagnostic'             => $fiche->getDiagnostic(),
            'examensComplementaires' => $fiche->getExamensComplementaires(),
            'diagnosticSupposeExamens' => $fiche->getDiagnosticSupposeExamens(),
            'traitementUrgence'      => $fiche->getTraitementUrgence(),
            'traitementDentaire'     => $fiche->getTraitementDentaire(),
            'traitementParodontal'   => $fiche->getTraitementParodontal(),
            'traitementOrthodontique'=> $fiche->getTraitementOrthodontique(),
            'autres'                 => $fiche->getAutres(),
        ];

        $examens = $fiche->getToothsCheck();

        $documents = [];
        foreach ($fiche->getDocumentsMedicaux() as $d) {
            $documents[] = [
                'libelle'     => $d->getLibelle(),
                'dateDossier' => $d->getDateDossier()->format('Y-m-d'),
                'description' => $d->getDescription(),
                'url'         => $d->getFichier(),
            ];
        }

        $devis = $fiche->getDevis()[0] ?? null;
        $devisData = null;
        if ($devis) {
            $contenus = [];
            foreach ($devis->getContenus() as $c) {
                $contenus[] = [

                    'designation' => $c->getDesignation(),
                    'qte'         => $c->getQte(),
                    'montant'     => $c->getMontant(),
                ];
            }
            $devisData = [
                'id'       => $devis->getId(),
                'date'     => $devis->getDate()->format('Y-m-d'),
                'contenus' => $contenus,
            ];
        }

        $precedentes = [];
        foreach ($fiche->getConsultations() as $s) {
            if ($s->getId() !== $consultation->getId() && $s->getStatut() === 1) {
                $precedentes[] = [
                    'id'         => $s->getId(),
                    'date'       => $s->getCreatedAt()->format('Y-m-d'),
                    'medecin'    => $s->getMedecin()   ? $s->getMedecin()->getFullName()   : null,
                    'infirmier'  => $s->getInfirmier() ? $s->getInfirmier()->getFullName() : null,
                    'salle'      => $s->getSalle()     ? $s->getSalle()->getNom()          : null,
                    'noteSeance' => $s->getNoteSeance(),
                ];
            }
        }

        $actes = [];
        foreach ($consultation->getActes() as $a) {
            $actes[] = [
                'dent'        => $a->getDent(),
                'type'        => $a->getType(),
                'description' => $a->getDescription(),
                'prix'        => $a->getPrix(),
                'quantite'    => $a->getQuantite(),
                'attribution' => $a->getAttribution(),
            ];
        }

        return [
            'patient'      => $patientData,
            'consultation' => $consultationData,
            'fiche'        => array_merge($ficheData, [
                'examens'       => $examens,
                'documents'     => $documents,
                'devis'         => $devisData,
                'consultations' => $precedentes,
            ]),
            'actes'        => $actes,
        ];
    }

    public function updateConsultation(int $ficheId, int $consultationId, array $data, ?object $user = null, bool $restrictToMedecin = false): void
    {
        [, $consultation] = $this->getFicheAndConsultation($ficheId, $consultationId, $user, $restrictToMedecin);
        $this->ensureConsultationOpen($consultation);

        $actorMedecin = $this->enforceMedecinOwnership($consultation, $user, $restrictToMedecin);
        $this->applyConsultationPayload($consultation, $data, $actorMedecin);

        $this->em->flush();

        $this->focusRealtimePublisher->publishConsultationRefresh($consultation, 'updated');
    }

    public function clotureConsultation(int $ficheId, int $consultationId, ?object $user = null, bool $restrictToMedecin = false, array $payload = []): void
    {
        [$fiche, $consultation] = $this->getFicheAndConsultation($ficheId, $consultationId, $user, $restrictToMedecin);
        $this->ensureConsultationOpen($consultation);

        $actorMedecin = $this->enforceMedecinOwnership($consultation, $user, $restrictToMedecin);

        if (!empty($payload)) {
            $this->applyConsultationPayload($consultation, $payload, $actorMedecin);
            $this->em->flush();
        }

        if (!$consultation->getMedecin()) {
            throw new \InvalidArgumentException('Le médecin est obligatoire pour clôturer la consultation.');
        }

        $factureAssurance = $this->ensureFactureAssurance($consultation);

        if ($factureAssurance !== null) {
            $factureAssurance->setInsuranceStatus('ready');
            $this->em->persist($factureAssurance);
            $this->em->flush();

            $consultation->setStatut(1);
            $this->em->flush();

            $this->focusRealtimePublisher->publishConsultationRefresh($consultation, 'closed');

            $notifyAmount = (float) ($factureAssurance->computeTotals()['montantPatient'] ?? 0.0);
            $this->notifyReceptionOnClosure($consultation, $notifyAmount);
        } else {
            $isNewFacture = !$consultation->getFacture();
            $facture = $consultation->getFacture() ?? new Facture();
            $facture->setConsultation($consultation);
            if ($isNewFacture) {
                $facture->setDateFacture($this->resolveFactureDateFromConsultation($consultation));
            }

            $montants = $facture->computeMontantsFromConsultation();
            $facture->setIsReglee(((float) ($montants['restePatient'] ?? 0.0)) <= 0.0);

            $consultation->setFacture($facture);
            $this->em->persist($facture);
            $this->em->flush();

            $consultation->setStatut(1);
            $this->em->flush();

            $this->focusRealtimePublisher->publishConsultationRefresh($consultation, 'closed');

            $this->notifyReceptionOnClosure($consultation, (float) $montants['montantTotal']);
        }
    }

    private function notifyReceptionOnClosure(Consultation $consultation, float $invoiceAmount): void
    {
        $recipients = $this->notificationRecipientResolver->receptionists();

        if ($recipients === []) {
            return;
        }

        $patient = $consultation->getPatient();
        $patientName = trim($patient?->getFullName() ?? '') ?: 'un patient';
        $amountLabel = number_format($invoiceAmount, 0, ',', ' ');
        $message = sprintf(
            'Consultation de %s clôturée : facture de %s FCFA prête en caisse.',
            $patientName,
            $amountLabel,
        );

        $this->eventDispatcher->dispatch(
            new EntityActionEvent(
                $consultation,
                'closed',
                ['ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE'],
                null,
                [
                    'message' => $message,
                    'priority' => 'info',
                    'type' => 'success',
                    'link' => NotificationLinkBuilder::CAISSE,
                ],
            )
        );
    }

    public function getClosedConsultationsData(): array
    {
        $consultations = $this->consultationRepo->findClosedConsultations();

        return array_map(function (Consultation $consultation) {
            $facture = $consultation->getFacture();

            return [
                'id' => $consultation->getId(),
                'patient' => $consultation->getPatient()?->getFullName(),
                'medecin' => $consultation->getMedecin()?->getFullName(),
                'date' => $consultation->getCreatedAt()?->format('Y-m-d H:i:s'),
                'salle' => $consultation->getSalle()?->getNom(),
                'state' => $consultation->getStatut(),
                'factstate' => $this->resolveFocusFactState($facture),
                'factModifiable' => $this->isFactureModifiable($facture),
                'patientId' => $consultation->getPatient()?->getId(),
            ];
        }, $consultations);
    }

    public function listMedecins(): array
    {
        return $this->cache->get('medecins.list', function (ItemInterface $item) {
            $item->expiresAfter(120);
            $employees = $this->employeRepo->FindAllMedecin();

            return array_map(function ($employee) {
                return [
                    'id' => $employee->getId(),
                    'nom' => $employee->getNom(),
                    'prenom' => $employee->getPrenom(),
                    'fullName' => $employee->getFullName(),
                    'fullname' => $employee->getFullName(),
                    'name' => $employee->getFullName(),
                    'label' => $employee->getFullName(),
                    'fonction' => $employee->getFonction(),
                    'type' => $employee->getType(),
                    'dateEmbauche' => $employee->getDateEmbauche()->format('Y-m-d'),
                    'comingDays' => $employee->getComingDaysInWeek(),
                ];
            }, $employees);
        });
    }

    public function listInfirmiers(): array
    {
        return $this->cache->get('infirmiers.list', function (ItemInterface $item) {
            $item->expiresAfter(120);
            $employees = $this->employeRepo->findAllInfirmiers();

            return array_map(function ($employee) {
                return [
                    'id' => $employee->getId(),
                    'nom' => $employee->getNom(),
                    'prenom' => $employee->getPrenom(),
                    'fullName' => $employee->getFullName(),
                    'fullname' => $employee->getFullName(),
                    'name' => $employee->getFullName(),
                    'label' => $employee->getFullName(),
                    'fonction' => $employee->getFonction(),
                    'type' => $employee->getType(),
                    'dateEmbauche' => $employee->getDateEmbauche()->format('Y-m-d'),
                    'comingDays' => $employee->getComingDaysInWeek(),
                ];
            }, $employees);
        });
    }

    public function invalidateStaffReferenceCache(): void
    {
        $this->cache->delete('medecins.list');
        $this->cache->delete('infirmiers.list');
    }

    public function getPendingConsultationsContext(): array
    {
        $consultations = $this->consultationRepo->findPendingConsultations();

        $consultationsData = array_map(function (Consultation $c) {
            $ficheData = $this->resolvePendingFicheData($c);

            return [
                'id' => $c->getId(),
                'patient' => $c->getPatient()->getNom() . ' ' . $c->getPatient()->getPrenom(),
                'medecin' => $c->getMedecin() ? $c->getMedecin()->getFullName() : null,
                'dateDebut' => $c->getCreatedAt()->format('Y-m-d H:i'),
                'hasFiche' => $ficheData['hasFiche'],
                'isPaid' => $c->getPaiement() ? true : false,
                'fiche' => $ficheData['fiche'],
                'ficheId' => $ficheData['ficheId'],
                'motif' => $ficheData['motif'],
                'lastFicheId' => $ficheData['lastFicheId'], 
            ];
        }, $consultations);

        return [
            'consultations' => $consultations,
            'consultationsData' => $consultationsData,
        ];
    }

    public function listPendingConsultationsJson(): array
    {
        $consults = $this->consultationRepo->findBy(['statut' => 0]);

        return array_map(function (Consultation $c) {
            $ficheData = $this->resolvePendingFicheData($c);

            return [
                'id' => $c->getId(),
                'patient' => $c->getPatient()->getNom() . ' ' . $c->getPatient()->getPrenom(),
                'medecin' => $c->getMedecin()?->getFullName(),
                'type' => $c->getType(),
                'motif' => $ficheData['motif'],
                'createdAt' => $c->getCreatedAt()->format('Y-m-d H:i'),
                'hasFiche' => $ficheData['hasFiche'],
                'ficheId' => $ficheData['ficheId'], 
                'lastFicheId' => $ficheData['lastFicheId'], 
            ];
        }, $consults);
    }

    public function listPendingConsultationsJsonForUser(?object $user, bool $restrictToMedecin): array
    {
        if ($restrictToMedecin) {
            $medecin = $this->getMedecinForUser($user);
            if (!$medecin) {
                return [];
            }
            $consults = array_values(array_filter(
                $this->consultationRepo->findPendingConsultations(),
                fn (Consultation $c) => !$c->getMedecin() || $c->getMedecin()?->getId() === $medecin->getId()
            ));
        } else {
            $consults = $this->consultationRepo->findBy(['statut' => 0]);
        }

        return array_map(function (Consultation $c) {
            $ficheData = $this->resolvePendingFicheData($c);

            return [
                'id' => $c->getId(),
                'patient' => $c->getPatient()->getNom() . ' ' . $c->getPatient()->getPrenom(),
                'medecin' => $c->getMedecin() ? $c->getMedecin()->getFullName() : null,
                'type' => $c->getType(),
                'motif' => $ficheData['motif'],
                'createdAt' => $c->getCreatedAt()->format('Y-m-d H:i'),
                'hasFiche' => $ficheData['hasFiche'],
                'ficheId' => $ficheData['ficheId'],
                'lastFicheId' => $ficheData['lastFicheId'],
            ];
        }, $consults);
    }

    private function lockPatientForFicheResolution(Patient $patient): void
    {
        if (!$patient->getId()) {
            return;
        }

        $this->em->createQueryBuilder()
            ->select('p')
            ->from(Patient::class, 'p')
            ->where('p.id = :id')
            ->setParameter('id', $patient->getId())
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    public function linkOrCreateFiche(
        int $consultationId,
        ?int $ficheId = null,
        ?object $user = null,
        bool $restrictToMedecin = false,
        bool $forceCreate = false,
        bool $allowDuplicate = false,
    ): array {
        $consultation = $this->consultationRepo->find($consultationId);

        if (!$consultation) {
            throw new NotFoundHttpException('Consultation introuvable');
        }

        $existingMedicalFiche = $consultation->getFicheMedicale();
        if ($consultation->getStatut() === 1) {
            $existingFicheId = $existingMedicalFiche?->getId();
            if ($existingFicheId !== null && ($ficheId === null || $ficheId === $existingFicheId) && !$forceCreate) {
                return [
                    'ficheId' => $existingFicheId,
                    'consultationId' => $consultation->getId(),
                    'created' => false,
                ];
            }

            throw new ConflictHttpException('Cette consultation est deja cloturee.');
        }

        $this->enforceMedecinOwnership($consultation, $user, $restrictToMedecin);
        $this->ensureConsultationOpen($consultation);

        $result = $this->em->wrapInTransaction(function () use (
            $consultationId,
            $ficheId,
            $forceCreate,
            $allowDuplicate,
        ): array {
            $consultation = $this->consultationRepo->find($consultationId);
            if (!$consultation) {
                throw new NotFoundHttpException('Consultation introuvable');
            }

            $patient = $consultation->getPatient();
            if (!$patient) {
                throw new \InvalidArgumentException('Consultation sans patient.');
            }

            $this->lockPatientForFicheResolution($patient);

            [$fiche, $created] = $this->resolveFicheForConsultation(
                $consultation,
                $ficheId,
                $forceCreate,
                $allowDuplicate,
            );

            $this->em->persist($consultation);
            $this->em->flush();

            return [
                'fiche' => $fiche,
                'consultation' => $consultation,
                'created' => $created,
            ];
        });

        $this->focusRealtimePublisher->publishConsultationRefresh(
            $result['consultation'],
            $result['created'] ? 'linked-created-fiche' : 'linked-fiche',
        );

        return [
            'ficheId' => $result['fiche']->getId(),
            'consultationId' => $result['consultation']->getId(),
            'created' => $result['created'],
        ];
    }

    public function getEditConsultationContext(int $id, bool $createNewFiche = false): array
    {
        $consultation = $this->consultationRepo->find($id);

        if (!$consultation) {
            throw new NotFoundHttpException('Consultation non trouvée.');
        }

        $fiche = $this->em->wrapInTransaction(function () use ($id, $createNewFiche): FicheMedicale {
            $consultation = $this->consultationRepo->find($id);
            if (!$consultation) {
                throw new NotFoundHttpException('Consultation non trouvée.');
            }

            $patient = $consultation->getPatient();
            if ($patient) {
                $this->lockPatientForFicheResolution($patient);
            }

            [$fiche] = $this->resolveFicheForConsultation(
                $consultation,
                null,
                $createNewFiche,
                $createNewFiche,
            );

            $this->em->persist($consultation);
            $this->em->flush();

            return $fiche;
        });

        $consultation = $this->consultationRepo->find($id);
        $medecins = $this->employeRepo->FindAllMedecin() ?? [];
        $infirmiers = $this->employeRepo->findBy(['type' => 'infirmier']);
        $salles = $this->salleRepo->findAll();

        return [
            'consultation' => $consultation,
            'fiche' => $consultation->getFicheMedicale() ?: $fiche,
            'consultationsFiche' => $fiche ? $fiche->getConsultations()->filter(fn($c) => $c->getStatut() === 1) : [],
            'medecins' => $medecins,
            'infirmiers' => $infirmiers,
            'salles' => $salles,
        ];
    }

    public function getConsultationDetailsContext(int $id): array
    {
        $consultation = $this->consultationRepo->findFullConsultation($id);

        if (!$consultation) {
            throw new NotFoundHttpException('Consultation introuvable');
        }

        return [
            'consultation' => $consultation,
            'actes' => $consultation->getActes(),
        ];
    }

    public function deleteConsultation(int $id, ?User $actor = null): bool
    {
        /** @var Consultation|null $consultation */
        $consultation = $this->consultationRepo->find($id);

        if (!$consultation) {
            return false;
        }

        /** @var Facture|null $facture */
        $facture = $consultation->getFacture();

        $paiementConsultation = $consultation->getPaiement();
        if ($paiementConsultation) {
            $transaction = $paiementConsultation->getTransaction();
            if ($transaction) {
                $transaction->setPaiement(null);
                $this->em->remove($transaction);
            }
            $paiementConsultation->setConsultation(null);
            $paiementConsultation->setFacture(null);
            $this->em->remove($paiementConsultation);
            $this->em->flush();
        }

        $factureAssurance = $consultation->getFactureAssurance();
        if ($factureAssurance) {
            foreach ($factureAssurance->getPaiements() as $paiement) {
                $transaction = $paiement->getTransaction();
                if ($transaction) {
                    $transaction->setPaiement(null);
                    $this->em->remove($transaction);
                }
                $paiement->setFactureAssurance(null);
                $this->em->remove($paiement);
            }
            $this->em->remove($factureAssurance);
            $this->em->flush();
        }

        if ($facture) {
            foreach ($facture->getPaiements() as $paiement) {
                $transaction = $paiement->getTransaction();
                if ($transaction) {
                    $transaction->setPaiement(null);
                    $this->em->remove($transaction);
                }
                $paiement->setFacture(null);
                $this->em->remove($paiement);
            }

            $consultation->setFacture(null);
            $facture->setConsultation(null);
            $this->em->remove($facture);
            $this->em->flush();
        }

        $this->em->remove($consultation);
        $this->em->flush();

        $this->focusRealtimePublisher->publishConsultationRefresh($consultation, 'deleted');

        $this->eventDispatcher->dispatch(
            new EntityActionEvent(
                $consultation,
                'cancelled',
                ['ROLE_MEDECIN'],
                $actor,
                [
                    'priority' => 'warning',
                    'type' => 'warning',
                    'link' => NotificationLinkBuilder::consultation(null, true),
                ],
            )
        );

        return true;
    }

    public function getFactureLines(Consultation $consultation): ?array
    {
        $facture = $consultation->getFacture();
        if (!$facture) {
            return null;
        }

        $lignes = [];

        foreach ($consultation->getActes() as $acte) {
            $dentValue = (string) ($acte->getDent() ?? '');
            $type = trim((string) ($acte->getType() ?? ''));
            $description = trim((string) ($acte->getDescription() ?? ''));
            if ($description === '') {
                $description = $type;
            }

            $lignes[] = [
                'type' => $type,
                'description' => $description,
                'quantite' => $acte->getQuantite(),
                'prix' => $acte->getPrix(),
                'dent' => $dentValue,
                'dents' => $this->normalizeDentList($dentValue),
                'attribution' => $acte->getAttribution(),
            ];
        }

        $dateFacture = $facture->getDateFacture();

        return [
            'lignes' => $lignes,
            'dateFacture' => $dateFacture?->format('Y-m-d'),
            'timeFacture' => $dateFacture?->format('H:i'),
            'modifiable' => $this->isFactureModifiable($facture),
        ];
    }

    public function updateFactureLines(Consultation $consultation, array $lignes, ?string $date = null, ?string $time = null): array
    {
        if (!is_array($lignes)) {
            return ['error' => 'Payload invalide'];
        }

        $facture = $consultation->getFacture();

        if (!$facture) {
            return ['error' => 'Facture non trouvée'];
        }

        if (!$this->isFactureModifiable($facture)) {
            return ['error' => 'Cette facture ne peut plus être modifiée car elle possède déjà des paiements.'];
        }

        // Re-synchronise les actes de la consultation avec les lignes soumises
        foreach ($consultation->getActes() as $oldActe) {
            $this->em->remove($oldActe);
        }
        $this->em->flush();

        foreach ($lignes as $ligneData) {
            $designation = $ligneData['type']
                ?? $ligneData['designation']
                ?? $ligneData['description']
                ?? '';
            $prix = (float) ($ligneData['prix'] ?? $ligneData['montant'] ?? 0);
            $quantite = (int) ($ligneData['quantite'] ?? $ligneData['qte'] ?? 1);
            $dent = $this->normalizeDentValue($ligneData['dent'] ?? ($ligneData['dents'] ?? ''));
            $description = trim((string) ($ligneData['description'] ?? ''));
            if ($description === '') {
                $description = (string) $designation;
            }

            $acte = new ActeMedical();
            $acte->setConsultation($consultation)
                ->setDent($dent)
                ->setType($designation)
                ->setDescription($description)
                ->setPrix($prix)
                ->setQuantite($quantite)
                ->setAttribution($this->actAttributionResolver->resolveFromPayload(is_array($ligneData) ? $ligneData : []));
            $this->em->persist($acte);
        }

        $factureAssurance = $consultation->getFactureAssurance();
        if ($factureAssurance !== null) {
            if ($consultation->getStatut() === 1) {
                $factureAssurance->setInsuranceStatus('ready');
            }
            $this->em->persist($factureAssurance);
        } else {
            $montants = $facture->computeMontantsFromConsultation();
            $facture->setIsReglee(((float) $montants['restePatient']) <= 0.0);
        }

        $facture->setDateFacture($this->parseFactureDateInput($date, $time, $consultation));

        $this->em->persist($facture);
        $this->em->flush();

        $this->focusRealtimePublisher->publishFactureRefresh($facture, 'updated');
        $this->focusRealtimePublisher->publishConsultationRefresh($consultation, 'invoice-updated');

        return ['success' => true];
    }

    public function consultationsDuJour(?string $dateStr, ?object $user): array
    {
        $consultations = $this->getConsultationsForDay($dateStr, $user);

        $data = [];
        foreach ($consultations as $c) {
            $ficheData = $this->resolvePendingFicheData($c);
            $patient = $c->getPatient();
            $data[] = [
                'id' => $c->getId(),
                'numero' => $c->getNumeroPassage(),
                'patient' => [
                    'id' => $patient->getId(),
                    'nom' => $patient->getNom(),
                    'prenom' => $patient->getPrenom(),
                    'telephone' => $patient->getTelephone(),
                    'photo' => $patient->getPhoto(),
                    'insuranceProfile' => $this->resolvePatientInsuranceProfile($patient),
                ],
                'patientName' => $patient->getFullName(),
                'patientId' => $patient->getId(),
                'patientCreatedAt' => $patient->getDateInscription()?->format(DATE_ATOM),
                'medecin' => $c->getMedecin()?->getFullName(),
                'createdAt' => $c->getCreatedAt()->format('d/m/Y H:i'),
                'factstate' => $this->resolveFocusFactState($c->getFacture()),
                'factModifiable' => $c->getStatut() === 1 && $this->isFactureModifiable($c->getFacture()),
                'state' => $c->getStatut(),
                'hasFiche' => $ficheData['hasFiche'],
                'fiche' => $ficheData['fiche'],
                'ficheId' => $ficheData['ficheId'],
                'lastFicheId' => $ficheData['lastFicheId'],
                'hasInsurance' => $c->getFactureAssurance() !== null || $this->patientHasActiveInsurance($patient),
                'assuranceNom' => $c->getFactureAssurance()?->getAssurance()?->getNom()
                    ?? $patient->getAssuranceProfile()?->getAssurance()?->getNom(),
            ];
        }

        return ['data' => $data];
    }

    public function listOrdonnances(Consultation $consultation): array
    {
        $rows = [];
        foreach ($consultation->getOrdonnances() as $ord) {
            $rows[] = [
                'id' => $ord->getId(),
                'date' => $ord->getDate()->format('Y-m-d'),
                'medecinNom' => $ord->getMedecinNom(),
                'note' => $ord->getNote(),
                'lignes' => array_map(fn(OrdonnanceLigne $l) => [
                    'id' => $l->getId(),
                    'designation' => $l->getDesignation(),
                    'posologie' => $l->getPosologie(),
                    'frequence' => $l->getFrequence(),
                    'duree' => $l->getDuree(),
                    'quantite' => $l->getQuantite(),
                    'instructions' => $l->getInstructions(),
                ], $ord->getLignes()->toArray()),
            ];
        }
        return $rows;
    }

    public function createOrdonnance(Consultation $consultation, array $payload): array
    {
        $ord = $this->createOrdonnanceEntityFromPayload($consultation, $payload);
        if (!$ord) {
            return ['error' => 'Aucune ligne fournie'];
        }

        $this->em->flush();

        return ['success' => true, 'id' => $ord->getId()];
    }

    private function createOrdonnanceEntityFromPayload(Consultation $consultation, array $payload): ?Ordonnance
    {
        $lignes = $payload['lignes'] ?? [];
        if (!is_array($lignes) || empty($lignes)) {
            return null;
        }

        $ord = new Ordonnance();
        $ord->setConsultation($consultation)
            ->setDate(isset($payload['date']) ? new \DateTime($payload['date']) : new \DateTime())
            ->setMedecinNom($payload['medecinNom'] ?? null)
            ->setNote($payload['note'] ?? null);

        foreach ($lignes as $line) {
            $ol = new OrdonnanceLigne();
            $ol->setOrdonnance($ord)
                ->setDesignation($line['designation'] ?? '')
                ->setPosologie($line['posologie'] ?? null)
                ->setFrequence($line['frequence'] ?? null)
                ->setDuree($line['duree'] ?? null)
                ->setQuantite(isset($line['quantite']) ? (int) $line['quantite'] : null)
                ->setInstructions($line['instructions'] ?? null);
            $this->em->persist($ol);
            $ord->addLigne($ol);
        }

        $this->em->persist($ord);
        return $ord;
    }

    public function getOrdonnanceData(int $id): ?array
    {
        $ord = $this->em->getRepository(Ordonnance::class)->find($id);
        if (!$ord) {
            return null;
        }
        return [
            'id' => $ord->getId(),
            'date' => $ord->getDate()->format('Y-m-d'),
            'medecinNom' => $ord->getMedecinNom(),
            'note' => $ord->getNote(),
            'consultationId' => $ord->getConsultation()?->getId(),
            'patient' => $ord->getConsultation()?->getPatient()?->getFullName(),
            'lignes' => array_map(fn(OrdonnanceLigne $l) => [
                'id' => $l->getId(),
                'designation' => $l->getDesignation(),
                'posologie' => $l->getPosologie(),
                'frequence' => $l->getFrequence(),
                'duree' => $l->getDuree(),
                'quantite' => $l->getQuantite(),
                'instructions' => $l->getInstructions(),
            ], $ord->getLignes()->toArray()),
        ];
    }

    public function updateOrdonnance(int $id, array $payload): ?array
    {
        $ord = $this->em->getRepository(Ordonnance::class)->find($id);
        if (!$ord) {
            return null;
        }

        $lignes = $payload['lignes'] ?? [];
        if (!is_array($lignes) || empty($lignes)) {
            throw new \InvalidArgumentException('Au moins une ligne de prescription est requise.');
        }

        if (isset($payload['date']) && is_string($payload['date']) && $payload['date'] !== '') {
            $ord->setDate(new \DateTime($payload['date']));
        }

        if (array_key_exists('medecinNom', $payload)) {
            $ord->setMedecinNom($payload['medecinNom']);
        }

        if (array_key_exists('note', $payload)) {
            $ord->setNote($payload['note']);
        }

        foreach ($ord->getLignes()->toArray() as $ligne) {
            $ord->removeLigne($ligne);
            $this->em->remove($ligne);
        }

        foreach ($lignes as $line) {
            if (!is_array($line)) {
                continue;
            }

            $ol = new OrdonnanceLigne();
            $ol->setOrdonnance($ord)
                ->setDesignation($line['designation'] ?? '')
                ->setPosologie($line['posologie'] ?? null)
                ->setFrequence($line['frequence'] ?? null)
                ->setDuree($line['duree'] ?? null)
                ->setQuantite(isset($line['quantite']) ? (int) $line['quantite'] : null)
                ->setInstructions($line['instructions'] ?? null);
            $this->em->persist($ol);
            $ord->addLigne($ol);
        }

        $this->em->flush();

        return $this->getOrdonnanceData($id);
    }
}

