<?php

namespace App\Controller\ApiPortalPatient;

use App\CareDelivery\Entity\Consultation;
use App\Communication\Entity\Notification;
use App\Communication\Repository\NotificationRepository;
use App\IdentityAccess\Entity\User;
use App\Billing\Entity\Devis;
use App\Billing\Entity\Transaction;
use App\Billing\Repository\DevisRepository;
use App\Billing\Repository\TransactionRepository;
use App\Patient\Entity\Patient;
use App\Patient\Repository\PatientRepository;
use App\Scheduling\Entity\Rdv;
use App\Scheduling\Repository\RdvRepository;
use App\CareDelivery\Repository\ConsultationRepository;
use App\Service\MercureAuthorizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/portal-patient/me', name: 'api_patient_me_')]
#[IsGranted('ROLE_PATIENT')]
final class PatientPortalController extends AbstractController
{
    public function __construct(
        private readonly PatientRepository $patientRepository,
        private readonly ConsultationRepository $consultationRepository,
        private readonly DevisRepository $devisRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly RdvRepository $rdvRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly MercureAuthorizationService $mercureAuthorizationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/consultations', name: 'consultations', methods: ['GET'])]
    public function consultations(): JsonResponse
    {
        $patient = $this->resolveAuthenticatedPatient();
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

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

        return $this->json([
            'patient' => $this->mapPatientIdentity($patient),
            'total' => count($items),
            'items' => $items,
        ]);
    }

    #[Route('/devis-factures', name: 'devis_factures', methods: ['GET'])]
    public function devisFactures(): JsonResponse
    {
        $patient = $this->resolveAuthenticatedPatient();
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        $qb = $this->devisRepository->createQueryBuilder('d')
            ->leftJoin('d.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->where('p = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('d.date', 'DESC')
            ->addOrderBy('d.id', 'DESC');

        $devisList = $qb->getQuery()->getResult();

        $items = array_map(fn(Devis $devis): array => [
            'id' => $devis->getId(),
            'date' => $devis->getDate()?->format(DATE_ATOM),
            'montant' => $devis->getMontant(),
            'reste' => $devis->getReste(),
            'statut' => $devis->getStatut(),
            'type' => $devis->getType(),
            'consultationId' => $devis->getConsultation()?->getId(),
            'isFacture' => $devis->getType() === 1,
        ], $devisList);

        return $this->json([
            'patient' => $this->mapPatientIdentity($patient),
            'total' => count($items),
            'items' => $items,
        ]);
    }

    #[Route('/paiements', name: 'paiements', methods: ['GET'])]
    public function paiements(): JsonResponse
    {
        $patient = $this->resolveAuthenticatedPatient();
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        $qb = $this->transactionRepository->createQueryBuilder('t')
            ->leftJoin('t.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('t.devis', 'd')->addSelect('d')
            ->leftJoin('t.modeDePaiement', 'm')->addSelect('m')
            ->where('p = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('t.dateTransaction', 'DESC')
            ->addOrderBy('t.id', 'DESC');

        $transactions = $qb->getQuery()->getResult();

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
            'devisId' => $t->getDevis()?->getId(),
            'recu' => [
                'label' => 'Recu #' . $t->getId(),
                'printDataUrl' => '/api/portal-patient/me/paiements/' . $t->getId() . '/recu',
            ],
        ], $transactions);

        return $this->json([
            'patient' => $this->mapPatientIdentity($patient),
            'total' => count($items),
            'items' => $items,
        ]);
    }

    #[Route('/paiements/{id}/recu', name: 'paiement_recu', methods: ['GET'])]
    public function recu(int $id): JsonResponse
    {
        $patient = $this->resolveAuthenticatedPatient();
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

        $qb = $this->transactionRepository->createQueryBuilder('t')
            ->leftJoin('t.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'p')->addSelect('p')
            ->leftJoin('t.devis', 'd')->addSelect('d')
            ->leftJoin('t.modeDePaiement', 'm')->addSelect('m')
            ->where('t.id = :id')
            ->andWhere('p = :patient')
            ->setParameter('id', $id)
            ->setParameter('patient', $patient)
            ->setMaxResults(1);

        $transaction = $qb->getQuery()->getOneOrNullResult();
        if (!$transaction instanceof Transaction) {
            return $this->json(['error' => 'Recu introuvable'], 404);
        }

        return $this->json([
            'transaction' => [
                'id' => $transaction->getId(),
                'date' => $transaction->getDateTransaction()?->format('Y-m-d H:i:s'),
                'montant' => $transaction->getMontant(),
                'type' => $transaction->getType(),
                'modePaiement' => $transaction->getModeDePaiement()?->getLibelle(),
                'description' => $transaction->getDescription(),
            ],
            'patient' => $this->mapPatientIdentity($patient),
        ]);
    }

    #[Route('/rdvs', name: 'rdvs', methods: ['GET'])]
    public function rdvs(): JsonResponse
    {
        $patient = $this->resolveAuthenticatedPatient();
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

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

        return $this->json([
            'patient' => $this->mapPatientIdentity($patient),
            'total' => count($items),
            'items' => $items,
        ]);
    }

    #[Route('/notifications', name: 'notifications', methods: ['GET'])]
    public function notifications(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Utilisateur non authentifie'], 401);
        }

        $notifications = $this->notificationRepository->findLatestForUser($user, 50);

        return $this->json([
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
        ]);
    }

    #[Route('/notifications/mercure', name: 'notifications_mercure', methods: ['GET'])]
    public function notificationsMercure(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Utilisateur non authentifie'], 401);
        }

        $subscription = $this->mercureAuthorizationService->buildSubscription($user);
        if ($subscription === null) {
            return $this->json(['error' => 'Impossible de generer la souscription Mercure'], 400);
        }

        return $this->json($subscription);
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $patient = $this->resolveAuthenticatedPatient();
        if (!$patient instanceof Patient) {
            return $this->json(['error' => 'Patient introuvable pour ce compte'], 404);
        }

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
            ->select('COUNT(d.id)')
            ->from(Devis::class, 'd')
            ->leftJoin('d.consultation', 'c')
            ->where('c.patient = :patient')
            ->setParameter('patient', $patient)
            ->getQuery()
            ->getSingleScalarResult();

        $transactionsCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Transaction::class, 't')
            ->leftJoin('t.consultation', 'c')
            ->where('c.patient = :patient')
            ->setParameter('patient', $patient)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->json([
            'patient' => $this->mapPatientIdentity($patient),
            'stats' => [
                'consultations' => $consultationsCount,
                'rdvs' => $rdvCount,
                'devisFactures' => $devisCount,
                'paiements' => $transactionsCount,
            ],
        ]);
    }

    private function resolveAuthenticatedPatient(): ?Patient
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        // Preferred mapping: explicit relation set from patient dossier account management.
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

    /** @return array{id:int|null,nom:string,prenom:string,numCarnet:string|null,telephone:string|null,email:string|null} */
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
}
