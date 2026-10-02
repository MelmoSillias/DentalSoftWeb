<?php

namespace App\Billing\Service;

use App\Billing\Entity\FactureCabinet;
use App\Billing\Entity\Paiement;
use App\Billing\Entity\ServiceCabinet;
use App\Billing\Entity\Transaction;
use App\Billing\Repository\FactureCabinetRepository;
use App\Billing\Repository\ModeDePaiementRepository;
use App\Billing\Repository\ServiceCabinetRepository;
use App\ClinicalRecord\Repository\FicheMedicaleRepository;
use App\Patient\Entity\Patient;
use App\Patient\Repository\PatientRepository;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

class CabinetServiceBillingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ServiceCabinetRepository $serviceRepo,
        private FactureCabinetRepository $factureRepo,
        private ModeDePaiementRepository $modeRepo,
        private PatientRepository $patientRepo,
        private FicheMedicaleRepository $ficheRepo,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createForPatient(int $patientId, array $payload): array
    {
        $patient = $this->patientRepo->find($patientId);
        if (!$patient instanceof Patient) {
            return ['error' => 'Patient introuvable', 'status' => 404];
        }

        $designation = trim((string) ($payload['designation'] ?? $payload['description'] ?? ''));
        if ($designation === '') {
            return ['error' => 'Le service cabinet est requis', 'status' => 400];
        }

        $quantite = max(1, (int) ($payload['quantite'] ?? 1));
        $prix = round(max(0.0, (float) ($payload['prix'] ?? 0)), 2);
        $montant = round($prix * $quantite, 2);
        if ($montant <= 0.0) {
            return ['error' => 'Le montant du service doit être supérieur à zéro', 'status' => 400];
        }

        $date = $this->parseDate($payload['date'] ?? null) ?? new \DateTime();
        $note = isset($payload['note']) && is_scalar($payload['note']) ? trim((string) $payload['note']) : null;

        $service = new ServiceCabinet();
        $service->setPatient($patient);
        $service->setFicheMedicale($this->ficheRepo->findLatestByPatient($patient));
        $service->setDesignation(mb_substr($designation, 0, 255));
        $service->setQuantite($quantite);
        $service->setPrix($prix);
        $service->setMontant($montant);
        $service->setDateRealisation($date);
        $service->setNote($note === '' ? null : $note);
        $service->setStatut(ServiceCabinet::STATUT_EFFECTUE);

        $facture = new FactureCabinet();
        $facture->setService($service);
        $facture->setDateFacture($date);
        $facture->setMontant($montant);
        $facture->setIsReglee(false);

        $this->em->persist($service);
        $this->em->persist($facture);
        $this->em->flush();

        return ['success' => true, 'data' => $this->mapService($service)];
    }

    /** @return list<array<string, mixed>> */
    public function listForPatient(int $patientId): array
    {
        $services = $this->serviceRepo->createQueryBuilder('s')
            ->leftJoin('s.facture', 'f')->addSelect('f')
            ->leftJoin('f.paiements', 'p')->addSelect('p')
            ->andWhere('s.patient = :patientId')
            ->setParameter('patientId', $patientId)
            ->orderBy('s.dateRealisation', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn (ServiceCabinet $service): array => $this->mapService($service), $services);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(?DateTimeInterface $start = null, ?DateTimeInterface $end = null, bool $includeCancelled = false): array
    {
        $qb = $this->serviceRepo->createQueryBuilder('s')
            ->leftJoin('s.patient', 'patient')->addSelect('patient')
            ->leftJoin('s.facture', 'f')->addSelect('f')
            ->leftJoin('f.paiements', 'p')->addSelect('p')
            ->orderBy('s.dateRealisation', 'DESC')
            ->addOrderBy('s.id', 'DESC');

        if ($start !== null && $end !== null) {
            $qb->andWhere('s.dateRealisation BETWEEN :start AND :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        if (!$includeCancelled) {
            $qb->andWhere('s.statut = :statut')
                ->setParameter('statut', ServiceCabinet::STATUT_EFFECTUE);
        }

        return array_map(fn (ServiceCabinet $service): array => $this->mapService($service), $qb->getQuery()->getResult());
    }

    /** @return array<string, mixed>|null */
    public function get(int $serviceId, bool $includePayments = true): ?array
    {
        $service = $this->serviceRepo->createQueryBuilder('s')
            ->leftJoin('s.patient', 'patient')->addSelect('patient')
            ->leftJoin('s.facture', 'f')->addSelect('f')
            ->leftJoin('f.paiements', 'p')->addSelect('p')
            ->leftJoin('p.mode', 'm')->addSelect('m')
            ->andWhere('s.id = :id')
            ->setParameter('id', $serviceId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$service instanceof ServiceCabinet) {
            return null;
        }

        return $this->mapService($service, $includePayments);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(int $serviceId, array $payload): array
    {
        $service = $this->serviceRepo->find($serviceId);
        if (!$service instanceof ServiceCabinet) {
            return ['error' => 'Service cabinet introuvable', 'status' => 404];
        }

        if ($service->isAnnule()) {
            return ['error' => 'Ce service cabinet est annulé', 'status' => 400];
        }

        $hasPayments = ($service->getFacture()?->getPaiements()->count() ?? 0) > 0;

        $designation = array_key_exists('designation', $payload) || array_key_exists('description', $payload)
            ? trim((string) ($payload['designation'] ?? $payload['description'] ?? ''))
            : $service->getDesignation();
        if ($designation === '') {
            return ['error' => 'Le service cabinet est requis', 'status' => 400];
        }

        $note = array_key_exists('note', $payload)
            ? (isset($payload['note']) && is_scalar($payload['note']) ? trim((string) $payload['note']) : null)
            : $service->getNote();

        $date = array_key_exists('date', $payload)
            ? ($this->parseDate($payload['date'] ?? null) ?? $service->getDateRealisation())
            : $service->getDateRealisation();

        $quantite = array_key_exists('quantite', $payload)
            ? max(1, (int) $payload['quantite'])
            : $service->getQuantite();
        $prix = array_key_exists('prix', $payload)
            ? round(max(0.0, (float) $payload['prix']), 2)
            : $service->getPrix();
        $montant = round($prix * $quantite, 2);

        if ($hasPayments && ($quantite !== $service->getQuantite() || abs($prix - $service->getPrix()) > 0.001 || abs($montant - $service->getMontant()) > 0.001)) {
            return ['error' => 'Impossible de modifier le montant d’un service déjà payé.', 'status' => 400];
        }

        if ($montant <= 0.0) {
            return ['error' => 'Le montant du service doit être supérieur à zéro', 'status' => 400];
        }

        $service->setDesignation(mb_substr($designation, 0, 255));
        $service->setNote($note === '' ? null : $note);
        if ($date instanceof \DateTimeInterface) {
            $service->setDateRealisation($date);
        }
        $service->setQuantite($quantite);
        $service->setPrix($prix);
        $service->setMontant($montant);

        $facture = $service->getFacture();
        if ($facture instanceof FactureCabinet && !$hasPayments) {
            $facture->setMontant($montant);
            if ($date instanceof \DateTimeInterface) {
                $facture->setDateFacture($date);
            }
            $facture->setIsReglee(false);
        }

        $this->em->flush();

        return ['success' => true, 'data' => $this->mapService($service, true)];
    }

    /** @return list<array<string, mixed>> */
    public function listForFiche(int $ficheId): array
    {
        $services = $this->serviceRepo->createQueryBuilder('s')
            ->leftJoin('s.facture', 'f')->addSelect('f')
            ->leftJoin('f.paiements', 'p')->addSelect('p')
            ->andWhere('s.ficheMedicale = :ficheId')
            ->setParameter('ficheId', $ficheId)
            ->orderBy('s.dateRealisation', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn (ServiceCabinet $service): array => $this->mapService($service), $services);
    }

    /** @return array<string, mixed> */
    public function cancel(int $serviceId): array
    {
        $service = $this->serviceRepo->find($serviceId);
        if (!$service instanceof ServiceCabinet) {
            return ['error' => 'Service cabinet introuvable', 'status' => 404];
        }

        if ($service->isAnnule()) {
            return ['success' => true, 'data' => $this->mapService($service)];
        }

        $paid = $service->getFacture()?->computePaidAmount() ?? 0.0;
        if ($paid > 0.0) {
            return ['error' => 'Ce service a déjà des paiements. Annulation impossible.', 'status' => 400];
        }

        $service->setStatut(ServiceCabinet::STATUT_ANNULE);
        $this->em->flush();

        return ['success' => true, 'data' => $this->mapService($service)];
    }

    /** @return list<array<string, mixed>> */
    public function listUnpaidForCashdesk(?DateTimeInterface $start, ?DateTimeInterface $end, ?int $patientId = null): array
    {
        $qb = $this->factureRepo->createQueryBuilder('f')
            ->leftJoin('f.service', 's')->addSelect('s')
            ->leftJoin('s.patient', 'p')->addSelect('p')
            ->leftJoin('f.paiements', 'pay')->addSelect('pay')
            ->leftJoin('pay.transaction', 't')->addSelect('t')
            ->andWhere('s.statut = :statut')
            ->setParameter('statut', ServiceCabinet::STATUT_EFFECTUE)
            ->orderBy('f.dateFacture', 'DESC');

        if ($start !== null && $end !== null) {
            $qb->andWhere('f.dateFacture BETWEEN :start AND :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }
        if ($patientId !== null && $patientId > 0) {
            $qb->andWhere('p.id = :patientId')->setParameter('patientId', $patientId);
        }

        $rows = [];
        foreach ($qb->getQuery()->getResult() as $facture) {
            if (!$facture instanceof FactureCabinet || $facture->computeReste() <= 0.0) {
                continue;
            }
            $rows[] = $this->mapFacture($facture);
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function listFacturesForCashdesk(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $factures = $this->factureRepo->createQueryBuilder('f')
            ->leftJoin('f.service', 's')->addSelect('s')
            ->leftJoin('s.patient', 'p')->addSelect('p')
            ->leftJoin('f.paiements', 'pay')->addSelect('pay')
            ->andWhere('f.dateFacture BETWEEN :start AND :end')
            ->andWhere('s.statut = :statut')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('statut', ServiceCabinet::STATUT_EFFECTUE)
            ->orderBy('f.dateFacture', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn (FactureCabinet $facture): array => $this->mapFacture($facture), $factures);
    }

    /** @return array<string, mixed>|null */
    public function previewFacture(int $factureId): ?array
    {
        $facture = $this->factureRepo->find($factureId);

        return $facture instanceof FactureCabinet ? $this->mapFacture($facture, true) : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function payFacture(int $factureId, array $payload): array
    {
        $facture = $this->factureRepo->find($factureId);
        if (!$facture instanceof FactureCabinet) {
            return ['error' => 'Facture cabinet introuvable', 'status' => 404];
        }

        $service = $facture->getService();
        if (!$service instanceof ServiceCabinet || $service->isAnnule()) {
            return ['error' => 'Ce service cabinet est annulé', 'status' => 400];
        }

        $remaining = $facture->computeReste();
        if ($remaining <= 0.0) {
            $facture->setIsReglee(true);
            $this->em->flush();

            return ['success' => true];
        }

        $mode = $this->modeRepo->find((int) ($payload['modeId'] ?? 0));
        $montant = round((float) ($payload['montant'] ?? $payload['amount'] ?? 0), 2);
        if (!$mode || $montant <= 0.0 || $montant > $remaining + 0.001) {
            return ['error' => 'Données invalides', 'status' => 400];
        }

        $timestamp = $this->parseDateTime($payload['date'] ?? null, $payload['time'] ?? null) ?? new \DateTime();

        $paiement = new Paiement();
        $facture->addPaiement($paiement);
        $paiement->setMode($mode);
        $paiement->setMontant($montant);
        $paiement->setDate($timestamp);

        $patientName = $service->getPatient()?->getFullName() ?? '';
        $transaction = new Transaction();
        $transaction->setType('Revenue');
        $transaction->setMontant((string) $montant);
        $transaction->setDateTransaction($timestamp);
        $transaction->setDescription(sprintf('Paiement service cabinet #%d | %s | %s', $facture->getId(), $service->getDesignation(), $patientName));
        $transaction->setMotif('Encaissement service cabinet');
        $transaction->setModeDePaiement($mode);
        $transaction->setRolePaiement('service_cabinet');
        $transaction->markValidated(\DateTimeImmutable::createFromInterface($timestamp));
        $transaction->setPaiement($paiement);

        $this->em->persist($transaction);
        $this->em->persist($paiement);

        $facture->setIsReglee($facture->computeReste() <= 0.0);
        $this->em->flush();

        return ['success' => true, 'paiement_id' => $paiement->getId(), 'paiementId' => $paiement->getId()];
    }

    /**
     * @return array{count: int, facture: float, encaisse: float, reste: float}
     */
    public function summarizePeriod(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $fromDate = \DateTime::createFromInterface($from);
        $toDate = \DateTime::createFromInterface($to);

        /** @var ServiceCabinet[] $services */
        $services = $this->serviceRepo->createQueryBuilder('s')
            ->leftJoin('s.facture', 'f')->addSelect('f')
            ->leftJoin('f.paiements', 'p')->addSelect('p')
            ->leftJoin('p.transaction', 't')->addSelect('t')
            ->andWhere('s.dateRealisation BETWEEN :from AND :to')
            ->andWhere('s.statut = :statut')
            ->setParameter('from', $fromDate)
            ->setParameter('to', $toDate)
            ->setParameter('statut', ServiceCabinet::STATUT_EFFECTUE)
            ->getQuery()
            ->getResult();

        $facture = 0.0;
        $reste = 0.0;
        foreach ($services as $service) {
            $amount = (float) ($service->getFacture()?->getMontant() ?? $service->getMontant());
            $facture += $amount;
            $reste += (float) ($service->getFacture()?->computeReste() ?? $amount);
        }

        $encaisse = 0.0;
        /** @var Paiement[] $payments */
        $payments = $this->em->createQueryBuilder()
            ->select('p', 't')
            ->from(Paiement::class, 'p')
            ->leftJoin('p.transaction', 't')
            ->andWhere('p.factureCabinet IS NOT NULL')
            ->andWhere('p.date BETWEEN :from AND :to')
            ->setParameter('from', $fromDate)
            ->setParameter('to', $toDate)
            ->getQuery()
            ->getResult();

        foreach ($payments as $payment) {
            $status = $payment->getTransaction()?->getValidationStatus();
            if ($status !== null && $status !== 'validated') {
                continue;
            }
            $encaisse += (float) $payment->getMontant();
        }

        return [
            'count' => count($services),
            'facture' => round($facture, 2),
            'encaisse' => round($encaisse, 2),
            'reste' => round($reste, 2),
        ];
    }

    /** @return array<string, mixed> */
    public function mapService(ServiceCabinet $service, bool $includePayments = false): array
    {
        $facture = $service->getFacture();
        $patient = $service->getPatient();
        $patientName = trim((string) ($patient?->getFullName() ?? ''));

        return [
            'id' => $service->getId(),
            'patientId' => $patient?->getId(),
            'patientName' => $patientName,
            'patient' => [
                'id' => $patient?->getId(),
                'nom' => $patient?->getNom() ?? '',
                'prenom' => $patient?->getPrenom() ?? '',
                'fullname' => $patientName,
                'telephone' => $patient?->getTelephone() ?? '',
            ],
            'ficheId' => $service->getFicheMedicale()?->getId(),
            'designation' => $service->getDesignation(),
            'quantite' => $service->getQuantite(),
            'prix' => $service->getPrix(),
            'montant' => $service->getMontant(),
            'date' => $service->getDateRealisation()?->format('Y-m-d H:i'),
            'note' => $service->getNote(),
            'statut' => $service->getStatut(),
            'facture' => $facture instanceof FactureCabinet ? $this->mapFacture($facture, $includePayments) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function mapFacture(FactureCabinet $facture, bool $includePayments = false): array
    {
        $service = $facture->getService();
        $patient = $service?->getPatient();
        $reste = $facture->computeReste();
        $paid = $facture->computePaidAmount();
        $isRegle = $facture->getMontant() > 0.0 ? $reste <= 0.0 : $facture->isReglee();
        $quantite = max(1, (int) ($service?->getQuantite() ?? 1));
        $prix = (float) ($service?->getPrix() ?? 0);

        $row = [
            'id' => $facture->getId(),
            'serviceId' => $service?->getId(),
            'date' => $facture->getDateFacture()?->format('Y-m-d') ?? (new \DateTime())->format('Y-m-d'),
            'consultation' => null,
            'designation' => $service?->getDesignation() ?? 'Service cabinet',
            'montant' => $facture->getMontant(),
            'montantTotal' => $facture->getMontant(),
            'montantPatient' => $facture->getMontant(),
            'montantAssureur' => 0.0,
            'reste' => $reste,
            'partPatientPayee' => $paid,
            'statut' => $isRegle ? 1 : 0,
            'isRegle' => $isRegle,
            'hasPayments' => $facture->getPaiements()->count() > 0,
            'patient' => [
                'id' => $patient?->getId(),
                'nom' => $patient?->getNom() ?? '',
                'prenom' => $patient?->getPrenom() ?? '',
                'telephone' => $patient?->getTelephone() ?? '',
            ],
            'patientId' => $patient?->getId(),
            'telephone' => $patient?->getTelephone(),
            'contenus' => [[
                'designation' => $service?->getDesignation() ?? 'Service cabinet',
                'description' => $service?->getNote() ?? '',
                'qte' => $quantite,
                'quantite' => $quantite,
                'montant' => $prix,
                'prix' => $prix,
                'total' => $facture->getMontant(),
            ]],
            'type' => 'ServiceCabinet',
            'kind' => 'service_cabinet',
            'insurance' => ['hasInsurance' => false, 'insuranceStatus' => 'none'],
        ];

        if ($includePayments) {
            $row['paiements'] = [];
            foreach ($facture->getPaiements() as $paiement) {
                $row['paiements'][] = [
                    'id' => $paiement->getId(),
                    'montant' => $paiement->getMontant(),
                    'date' => $paiement->getDate()?->format('Y-m-d H:i'),
                    'mode' => $paiement->getMode()?->getLibelle(),
                ];
            }
        }

        return $row;
    }

    private function parseDate(mixed $value): ?\DateTime
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        try {
            return new \DateTime((string) $value);
        } catch (\Exception) {
            return null;
        }
    }

    private function parseDateTime(mixed $date, mixed $time): ?\DateTime
    {
        if (is_scalar($date) && is_string($date) && str_contains($date, 'T')) {
            return $this->parseDate($date);
        }

        if (is_scalar($date) && is_scalar($time) && trim((string) $date) !== '' && trim((string) $time) !== '') {
            return $this->parseDate(trim((string) $date).' '.trim((string) $time));
        }

        return $this->parseDate($date);
    }
}
