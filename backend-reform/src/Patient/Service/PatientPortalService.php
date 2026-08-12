<?php

namespace App\Patient\Service;

use App\Billing\Infrastructure\Persistence\Doctrine\Entity\Facture;
use App\Billing\Infrastructure\Persistence\Doctrine\Entity\Transaction;
use App\Billing\Infrastructure\Persistence\Doctrine\Repository\FactureRepository;
use App\Billing\Infrastructure\Persistence\Doctrine\Repository\TransactionRepository;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Repository\ConsultationRepository;
use App\Communication\Infrastructure\Persistence\Doctrine\Entity\Notification;
use App\Communication\Infrastructure\Persistence\Doctrine\Repository\NotificationRepository;
use App\Communication\Service\MercureAuthorizationService;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Patient;
use App\Patient\Infrastructure\Persistence\Doctrine\Repository\PatientRepository;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\Rdv;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Repository\RdvRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PatientPortalService
{
    public function __construct(
        private readonly PatientRepository $patientRepository,
        private readonly ConsultationRepository $consultationRepository,
        private readonly FactureRepository $factureRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly RdvRepository $rdvRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly MercureAuthorizationService $mercureAuthorizationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function resolveAuthenticatedPatient(?object $user): ?Patient
    {
        if (!$user instanceof User) {
            return null;
        }

        $linked = $user->getPortalPatient();
        if ($linked instanceof Patient) {
            return $linked;
        }

        $identifier = (string) $user->getUserIdentifier();
        if ($identifier === '') {
            return null;
        }

        return $this->patientRepository->findOneByPortalIdentifier($identifier);
    }

    /** @return array{patient:array{id:int|null,nom:string,prenom:string,numCarnet:?string,telephone:?string,email:?string},total:int,items:array<int,array<string,mixed>>} */
    public function buildConsultationsPayload(Patient $patient): array
    {
        $consultations = $this->consultationRepository->findBy(
            ['patient' => $patient],
            ['CreatedAt' => 'DESC', 'id' => 'DESC']
        );

        $items = array_map(fn(Consultation $c): array => [
            'id' => $c->getId(),
            'date' => $c->getCreatedAt()?->format(DATE_ATOM),
            'type' => $c->getType(),
            'statut' => $c->getStatut(),
            'noteSeance' => $c->getNoteSeance(),
            'medecin' => $c->getMedecin() ? [
                'id' => $c->getMedecin()?->getId(),
                'nom' => trim((string) (($c->getMedecin()?->getNom() ?? '') . ' ' . ($c->getMedecin()?->getPrenom() ?? ''))),
            ] : null,
        ], $consultations);

        return [
            'patient' => $this->mapPatientIdentity($patient),
            'total' => count($items),
            'items' => $items,
        ];
    }

    /** @return array{patient:array{id:int|null,nom:string,prenom:string,numCarnet:?string,telephone:?string,email:?string},total:int,items:array<int,array<string,mixed>>} */
    public function buildDevisFacturesPayload(Patient $patient): array
    {
        $factureList = $this->factureRepository->findByPortalPatient($patient);

        $items = array_map(fn(Facture $facture): array => [
            'id' => $facture->getId(),
            'date' => $facture->getDateFacture()?->format(DATE_ATOM),
            'montant' => $facture->computeMontantsFromConsultation()['montantTotal'] ?? 0,
            'reste' => $facture->computeMontantsFromConsultation()['restePatient'] ?? 0,
            'statut' => $facture->isReglee() ? 'reglee' : 'ouverte',
            'type' => 'facture',
            'consultationId' => $facture->getConsultation()?->getId(),
            'isFacture' => true,
        ], $factureList);

        return [
            'patient' => $this->mapPatientIdentity($patient),
            'total' => count($items),
            'items' => $items,
        ];
    }

    /** @return array{patient:array{id:int|null,nom:string,prenom:string,numCarnet:?string,telephone:?string,email:?string},total:int,items:array<int,array<string,mixed>>} */
    public function buildPaiementsPayload(Patient $patient): array
    {
        $transactions = $this->transactionRepository->findByPortalPatient($patient);

        $items = array_map(fn(Transaction $t): array => [
            'id' => $t->getId(),
            'date' => $t->getDateTransaction()?->format(DATE_ATOM),
            'montant' => $t->getMontant(),
            'type' => $t->getType(),
            'description' => $t->getDescription(),
            'validationStatus' => $t->getValidationStatus(),
            'validated' => $t->isValidated(),
            'modePaiement' => $t->getModeDePaiement()?->getLibelle(),
            'consultationId' => $t->getConsultation()?->getId(),
            'factureId' => $t->getFacture()?->getId(),
            'recu' => [
                'label' => 'Recu #' . $t->getId(),
                'printDataUrl' => '/api/portal-patient/me/paiements/' . $t->getId() . '/recu',
            ],
        ], $transactions);

        return [
            'patient' => $this->mapPatientIdentity($patient),
            'total' => count($items),
            'items' => $items,
        ];
    }

    /** @return array{transaction:array{id:int|null,date:?string,montant:mixed,type:mixed,modePaiement:?string,description:mixed},patient:array{id:int|null,nom:string,prenom:string,numCarnet:?string,telephone:?string,email:?string}}|null */
    public function buildReceiptPayload(Patient $patient, int $transactionId): ?array
    {
        $transaction = $this->transactionRepository->findPortalReceiptById($patient, $transactionId);
        if (!$transaction instanceof Transaction) {
            return null;
        }

        return [
            'transaction' => [
                'id' => $transaction->getId(),
                'date' => $transaction->getDateTransaction()?->format('Y-m-d H:i:s'),
                'montant' => $transaction->getMontant(),
                'type' => $transaction->getType(),
                'modePaiement' => $transaction->getModeDePaiement()?->getLibelle(),
                'description' => $transaction->getDescription(),
            ],
            'patient' => $this->mapPatientIdentity($patient),
        ];
    }

    /** @return array{patient:array{id:int|null,nom:string,prenom:string,numCarnet:?string,telephone:?string,email:?string},total:int,items:array<int,array<string,mixed>>} */
    public function buildRdvsPayload(Patient $patient): array
    {
        $rdvs = $this->rdvRepository->findBy(
            ['patient' => $patient],
            ['dateRdv' => 'DESC', 'id' => 'DESC']
        );

        $items = array_map(fn(Rdv $rdv): array => [
            'id' => $rdv->getId(),
            'dateRdv' => $rdv->getDateRdv()?->format(DATE_ATOM),
            'dateCreation' => $rdv->getDateCreation()?->format(DATE_ATOM),
            'statut' => $rdv->getStatut(),
            'description' => $rdv->getDescription(),
            'duree' => $rdv->getDuration(),
            'medecin' => $rdv->getMedecin() ? [
                'id' => $rdv->getMedecin()?->getId(),
                'nom' => trim((string) (($rdv->getMedecin()?->getNom() ?? '') . ' ' . ($rdv->getMedecin()?->getPrenom() ?? ''))),
            ] : null,
        ], $rdvs);

        return [
            'patient' => $this->mapPatientIdentity($patient),
            'total' => count($items),
            'items' => $items,
        ];
    }

    /** @return array{total:int,items:array<int,array<string,mixed>>} */
    public function buildNotificationsPayload(User $user): array
    {
        $notifications = $this->notificationRepository->findLatestForUser($user, 50);

        return [
            'total' => count($notifications),
            'items' => array_map(fn(Notification $n): array => [
                'id' => $n->getId(),
                'message' => $n->getMessage(),
                'type' => $n->getType(),
                'priority' => $n->getPriority(),
                'status' => $n->getEtatVu(),
                'date' => $n->getDateEnvoi()?->format(DATE_ATOM),
                'link' => $n->getLink(),
            ], $notifications),
        ];
    }

    /** @return array<string,mixed>|null */
    public function buildMercureSubscription(User $user): ?array
    {
        return $this->mercureAuthorizationService->buildSubscription($user);
    }

    /** @return array{patient:array{id:int|null,nom:string,prenom:string,numCarnet:?string,telephone:?string,email:?string},stats:array{consultations:int,rdvs:int,devisFactures:int,paiements:int}} */
    public function buildDashboardPayload(Patient $patient): array
    {
        $consultationsCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Consultation::class, 'c')
            ->where('c.patient = :patient')
            ->setParameter('patient', $patient)
            ->getQuery()
            ->getSingleScalarResult();

        $rdvCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Rdv::class, 'r')
            ->where('r.patient = :patient')
            ->setParameter('patient', $patient)
            ->getQuery()
            ->getSingleScalarResult();

        $devisCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from(Facture::class, 'f')
            ->leftJoin('f.consultation', 'c')
            ->where('c.patient = :patient')
            ->setParameter('patient', $patient)
            ->getQuery()
            ->getSingleScalarResult();

        $transactionsCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Transaction::class, 't')
            ->leftJoin('t.consultation', 'c')
            ->leftJoin('t.facture', 'f')
            ->leftJoin('f.consultation', 'fc')
            ->where('c.patient = :patient OR fc.patient = :patient')
            ->setParameter('patient', $patient)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'patient' => $this->mapPatientIdentity($patient),
            'stats' => [
                'consultations' => $consultationsCount,
                'rdvs' => $rdvCount,
                'devisFactures' => $devisCount,
                'paiements' => $transactionsCount,
            ],
        ];
    }

    /** @return array{id:int|null,nom:string,prenom:string,numCarnet:?string,telephone:?string,email:?string} */
    private function mapPatientIdentity(Patient $patient): array
    {
        return [
            'id' => $patient->getId(),
            'nom' => (string) $patient->getNom(),
            'prenom' => (string) $patient->getPrenom(),
            'numCarnet' => $patient->getNumCarnet(),
            'telephone' => $patient->getTelephone(),
            'email' => $patient->getEmail(),
        ];
    }

    /** @return array{patient:array<string,mixed>,assurance:array<string,mixed>|null} */
    public function buildProfilePayload(Patient $patient): array
    {
        $profile = $patient->getAssuranceProfile();
        $assurance = null;
        if ($profile !== null) {
            $assuranceEntity = $profile->getAssurance();
            $assurance = [
                'nom'          => $assuranceEntity?->getNom(),
                'code'         => $assuranceEntity?->getCode(),
                'coverageRate' => $profile->getCoverageRate(),
                'formData'     => $profile->getFormData(),
            ];
        }

        return [
            'patient' => [
                'id'            => $patient->getId(),
                'nom'           => (string) $patient->getNom(),
                'prenom'        => (string) $patient->getPrenom(),
                'numCarnet'     => $patient->getNumCarnet(),
                'telephone'     => $patient->getTelephone(),
                'email'         => $patient->getEmail(),
                'sexe'          => $patient->getSexe(),
                'adresse'       => $patient->getAdresse(),
                'dateNaissance' => $patient->getDateNaissance()?->format('Y-m-d'),
            ],
            'assurance' => $assurance,
        ];
    }

    /** @return array<string,mixed>|null */
    public function buildConsultationDetailPayload(Patient $patient, int $consultationId): ?array
    {
        $consultation = $this->consultationRepository->find($consultationId);
        if ($consultation === null || $consultation->getPatient()?->getId() !== $patient->getId()) {
            return null;
        }

        $actes = [];
        $totalActes = 0.0;
        foreach ($consultation->getActes() as $a) {
            $qty   = max(1, (int) ($a->getQuantite() ?? 1));
            $prix  = (float) ($a->getPrix() ?? 0);
            $total = $qty * $prix;
            $totalActes += $total;
            $actes[] = [
                'dent'        => (string) ($a->getDent() ?? ''),
                'type'        => $a->getType(),
                'description' => $a->getDescription(),
                'prix'        => $prix,
                'quantite'    => $qty,
                'total'       => $total,
            ];
        }

        return [
            'id'          => $consultation->getId(),
            'date'        => $consultation->getCreatedAt()?->format(DATE_ATOM),
            'type'        => $consultation->getType(),
            'statut'      => $consultation->getStatut(),
            'noteSeance'  => $consultation->getNoteSeance(),
            'medecin'     => $consultation->getMedecin() ? [
                'id'  => $consultation->getMedecin()?->getId(),
                'nom' => trim((string) (
                    ($consultation->getMedecin()?->getNom() ?? '') . ' ' .
                    ($consultation->getMedecin()?->getPrenom() ?? '')
                )),
            ] : null,
            'actes'      => $actes,
            'totalActes' => $totalActes,
        ];
    }

    /** @return array<string,mixed>|null */
    public function buildDocumentDetailPayload(Patient $patient, int $factureId): ?array
    {
        $facture = $this->factureRepository->find($factureId);
        if ($facture === null || $facture->getConsultation()?->getPatient()?->getId() !== $patient->getId()) {
            return null;
        }

        $montants = $facture->computeMontantsFromConsultation();
        $lignes   = $facture->buildLignesFromConsultation();

        return [
            'id'             => $facture->getId(),
            'date'           => $facture->getDateFacture()?->format(DATE_ATOM),
            'statut'         => $facture->isReglee() ? 'reglee' : 'ouverte',
            'montantTotal'   => $montants['montantTotal'] ?? 0,
            'montantPatient' => $montants['montantPatient'] ?? 0,
            'patientPaid'    => $montants['patientPaid'] ?? 0,
            'restePatient'   => $montants['restePatient'] ?? 0,
            'consultationId' => $facture->getConsultation()?->getId(),
            'lignes'         => $lignes,
        ];
    }
}
