<?php

namespace App\Billing\Service;

use App\Billing\Entity\ContenuDevis;
use App\Billing\Entity\Devis;
use App\Billing\Entity\PaiementDevis;
use App\Billing\Entity\Transaction;
use App\Billing\Repository\DevisRepository;
use App\Billing\Repository\ModeDePaiementRepository;
use App\Billing\Repository\PaiementDevisRepository;
use App\Billing\Repository\TransactionRepository;
use App\Focus\Service\FocusRealtimePublisher;
use App\Patient\Entity\Patient;
use App\Settings\Service\GlobalSettingsService;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

class CashdeskService
{
    public function __construct(
        private DevisRepository $devisRepo,
        private PaiementDevisRepository $paiementRepo,
        private ModeDePaiementRepository $modeRepo,
        private TransactionRepository $transactionRepo,
        private EntityManagerInterface $em,
        private GlobalSettingsService $globalSettingsService,
        private FocusRealtimePublisher $focusRealtimePublisher,
    ) {
    }

    private function buildInsuranceMetadata(Devis $devis): array
    {
        $insuranceTransactions = $this->transactionRepo->createQueryBuilder('t')
            ->leftJoin('t.modeDePaiement', 'm')->addSelect('m')
            ->where('t.devis = :devis')
            ->andWhere('t.rolePaiement = :role')
            ->setParameter('devis', $devis)
            ->setParameter('role', 'insurance')
            ->orderBy('t.dateTransaction', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        $insurancePayments = [];
        $patientPaidAmount = 0.0;

        foreach ($devis->getPaiements() as $payment) {
            $role = $payment->getRolePaiement();
            if ($role === 'insurance') {
                $insurancePayments[] = $payment;
                continue;
            }

            $patientPaidAmount += (float) $payment->getMontant();
        }

        usort($insurancePayments, static function (PaiementDevis $left, PaiementDevis $right): int {
            $leftDate = $left->getDate()?->getTimestamp() ?? 0;
            $rightDate = $right->getDate()?->getTimestamp() ?? 0;

            if ($leftDate === $rightDate) {
                return ($right->getId() ?? 0) <=> ($left->getId() ?? 0);
            }

            return $rightDate <=> $leftDate;
        });

        $latestTransaction = $insuranceTransactions[0] ?? null;
        $latestPayment = $insurancePayments[0] ?? null;
        $referenceMode = $latestPayment?->getMode() ?? $latestTransaction?->getModeDePaiement();
        $insuranceAmount = (float) ($latestPayment?->getMontant() ?? $latestTransaction?->getMontant() ?? 0);
        $insurancePaidAmount = array_reduce(
            $insurancePayments,
            static fn (float $sum, PaiementDevis $payment): float => $sum + (float) $payment->getMontant(),
            0.0
        );
        $insurancePendingAmount = array_reduce(
            $insuranceTransactions,
            static fn (float $sum, Transaction $transaction): float => $sum + ($transaction->getValidationStatus() === 'pending' ? (float) $transaction->getMontant() : 0.0),
            0.0
        );

        if ($latestPayment !== null) {
            $status = 'validated';
        } elseif ($latestTransaction !== null) {
            $status = $latestTransaction->getValidationStatus();
        } else {
            $status = 'none';
        }

        return [
            'hasInsurance' => $latestTransaction !== null || $latestPayment !== null,
            'insuranceStatus' => $status,
            'insuranceModeId' => $referenceMode?->getId(),
            'insuranceModeLabel' => $referenceMode?->getLibelle(),
            'insuranceRate' => $latestPayment?->getTauxPriseEnCharge() ?? $latestTransaction?->getTauxPriseEnCharge(),
            'insuranceAmount' => $insuranceAmount,
            'insurancePaidAmount' => $insurancePaidAmount,
            'insurancePendingAmount' => $insurancePendingAmount,
            'insuranceTransactionId' => $latestTransaction?->getId(),
            'insurancePaymentId' => $latestPayment?->getId(),
            'patientPaidAmount' => $patientPaidAmount,
            'patientRemainingAmount' => max(0.0, (float) $devis->getReste()),
        ];
    }

    private function mapFactureListItem(Devis $devis, bool $consultationIsGuaranteed = false): array
    {
        $patient = $devis->getFicheMedicale()?->getPatient() ?? $devis->getFiche()?->getPatient();
        $consultationId = $consultationIsGuaranteed
            ? $devis->getConsultation()->getId()
            : $devis->getConsultation()?->getId();

        return [
            'id' => $devis->getId(),
            'date' => $devis->getDate()->format('Y-m-d'),
            'consultation' => $consultationId,
            'montant' => $devis->getMontant(),
            'reste' => $devis->getReste(),
            'statut' => $devis->getStatut(),
            'isRegle' => $devis->getStatut() == 1,
            'patient' => [
                'nom' => $patient?->getNom() ?? '',
                'prenom' => $patient?->getPrenom() ?? '',
            ],
            'telephone' => $patient?->getTelephone(),
            'insurance' => $this->buildInsuranceMetadata($devis),
        ];
    }

    private function buildDevisPaymentDetails(Devis $devis): array
    {
        $details = [];

        foreach ($devis->getPaiements() as $payment) {
            $transaction = $payment->getTransaction();
            $details[] = [
                'id' => $payment->getId(),
                'sourceType' => 'payment',
                'rolePaiement' => $payment->getRolePaiement(),
                'mode' => $payment->getMode()?->getLibelle(),
                'modeId' => $payment->getMode()?->getId(),
                'montant' => $payment->getMontant(),
                'date' => $payment->getDate()?->format('Y-m-d H:i:s'),
                'status' => $transaction?->getValidationStatus() ?? 'validated',
                'insuranceRate' => $payment->getTauxPriseEnCharge(),
                'description' => $transaction?->getDescription(),
            ];
        }

        $pendingInsuranceTransactions = $this->transactionRepo->createQueryBuilder('t')
            ->leftJoin('t.modeDePaiement', 'm')->addSelect('m')
            ->where('t.devis = :devis')
            ->andWhere('t.rolePaiement = :role')
            ->andWhere('t.paiementDevis IS NULL')
            ->setParameter('devis', $devis)
            ->setParameter('role', 'insurance')
            ->orderBy('t.dateTransaction', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($pendingInsuranceTransactions as $transaction) {
            $details[] = [
                'id' => $transaction->getId(),
                'sourceType' => 'transaction',
                'rolePaiement' => $transaction->getRolePaiement(),
                'mode' => $transaction->getModeDePaiement()?->getLibelle(),
                'modeId' => $transaction->getModeDePaiement()?->getId(),
                'montant' => (float) ($transaction->getMontant() ?? 0),
                'date' => $transaction->getDateTransaction()?->format('Y-m-d H:i:s'),
                'status' => $transaction->getValidationStatus(),
                'insuranceRate' => $transaction->getTauxPriseEnCharge(),
                'description' => $transaction->getDescription(),
            ];
        }

        usort($details, static function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['date'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['date'] ?? '')) ?: 0;

            if ($leftTime === $rightTime) {
                return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
            }

            return $rightTime <=> $leftTime;
        });

        return $details;
    }

    private function hasInsuranceAlreadyRecorded(Devis $devis): bool
    {
        $insuranceTransactionCount = (int) $this->transactionRepo->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.devis = :devis')
            ->andWhere('t.rolePaiement = :role')
            ->setParameter('devis', $devis)
            ->setParameter('role', 'insurance')
            ->getQuery()
            ->getSingleScalarResult();

        if ($insuranceTransactionCount > 0) {
            return true;
        }

        foreach ($devis->getPaiements() as $payment) {
            if ($payment->getRolePaiement() === 'insurance') {
                return true;
            }
        }

        return false;
    }

    public function listDevisByPeriod(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $devis = $this->devisRepo->createQueryBuilder('d')
            ->leftJoin('d.fiche', 'f')->addSelect('f')
            ->leftJoin('f.patient', 'p')->addSelect('p')
            ->leftJoin('d.ficheMedicale', 'fm')->addSelect('fm')
            ->leftJoin('fm.patient', 'pm')->addSelect('pm')
            ->join('App\\Entity\\Consultation', 'c', 'WITH', 'c.facture = d.id')
            ->where('d.type = :type')
            ->andWhere('d.date BETWEEN :start AND :end')
            ->setParameter('type', 1)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('d.date', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(function (Devis $d) {
            return $this->mapFactureListItem($d);
        }, $devis);
    }

    public function listDevisImpayes(): array
    {
        $devis = $this->devisRepo->createQueryBuilder('d')
            ->leftJoin('d.fiche', 'f')->addSelect('f')
            ->leftJoin('f.patient', 'p')->addSelect('p')
            ->leftJoin('d.ficheMedicale', 'fm')->addSelect('fm')
            ->leftJoin('fm.patient', 'pm')->addSelect('pm')
            ->join('App\\Entity\\Consultation', 'c', 'WITH', 'c.facture = d.id')
            ->where('d.statut = 0')
            ->andWhere('d.type = 1')
            ->orderBy('d.date', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(function (Devis $d) {
            return $this->mapFactureListItem($d, true);
        }, $devis);
    }

    public function listDevisImpayesByPatient(int $patientId): array
    {
        $devis = $this->devisRepo->createQueryBuilder('d')
            ->leftJoin('d.fiche', 'f')->addSelect('f')
            ->leftJoin('f.patient', 'p')->addSelect('p')
            ->leftJoin('d.ficheMedicale', 'fm')->addSelect('fm')
            ->leftJoin('fm.patient', 'pm')->addSelect('pm')
            ->join('App\\Entity\\Consultation', 'c', 'WITH', 'c.facture = d.id')
            ->where('d.statut = 0')
            ->andWhere('d.type = 1')
            ->andWhere('(p.id = :patientId OR pm.id = :patientId)')
            ->setParameter('patientId', $patientId)
            ->orderBy('d.date', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(function (Devis $d) {
            return $this->mapFactureListItem($d, true);
        }, $devis);
    }

    public function listDevisByPatient(int $patientId): array
    {
        $devis = $this->devisRepo->createQueryBuilder('d')
            ->leftJoin('d.fiche', 'f')->addSelect('f')
            ->leftJoin('f.patient', 'p')->addSelect('p')
            ->leftJoin('d.ficheMedicale', 'fm')->addSelect('fm')
            ->leftJoin('fm.patient', 'pm')->addSelect('pm')
            ->join('App\\Entity\\Consultation', 'c', 'WITH', 'c.facture = d.id')
            ->andWhere('d.type = 1')
            ->andWhere('(p.id = :patientId OR pm.id = :patientId)')
            ->setParameter('patientId', $patientId)
            ->orderBy('d.date', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(function (Devis $d) {
            return $this->mapFactureListItem($d, true);
        }, $devis);
    }

    public function listPaiementsDevis(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $paiements = $this->paiementRepo->createQueryBuilder('p')
            ->leftJoin('p.devis', 'd')->addSelect('d')
            ->leftJoin('d.fiche', 'f')->addSelect('f')
            ->leftJoin('f.patient', 'pat')->addSelect('pat')
            ->leftJoin('d.ficheMedicale', 'fm')->addSelect('fm')
            ->leftJoin('fm.patient', 'pm')->addSelect('pm')
            ->join('p.mode', 'm')->addSelect('m')
            ->where('p.date BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(function (PaiementDevis $p) {
            $devis = $p->getDevis();

            if ($p->getConsultation()) {
                $patient = $p->getConsultation()->getPatient();
            } elseif ($p->getDevis() && $p->getDevis()->getFicheMedicale()?->getPatient()) {
                $patient = $p->getDevis()->getFicheMedicale()->getPatient();
            } elseif ($p->getDevis() && $p->getDevis()->getFiche()?->getPatient()) {
                $patient = $p->getDevis()->getFiche()->getPatient();
            } else {
                $patient = null;
            }

            return [
                'devisId' => $devis ? $devis->getId() : $p->getId(),
                'patient' => $patient ? $patient->getFullName() : 'Anonyme',
                'telephone' => $patient?->getTelephone(),
                'montant' => $p->getMontant(),
                'mode' => $p->getMode()->getLibelle(),
                'modeId' => $p->getMode()->getId(),
                'date' => $p->getDate()->format('Y-m-d H:i:s'),
                'type' => $devis ? 'devis' : 'ticket',
                'pId' => $p->getId(),
                'rolePaiement' => $p->getRolePaiement(),
                'insuranceRate' => $p->getTauxPriseEnCharge(),
                'insuranceStatus' => $p->getRolePaiement() === 'insurance' ? ($p->getTransaction()?->getValidationStatus() ?? 'validated') : 'validated',
            ];
        }, $paiements);
    }

    public function listPaiementsDevisByPatients(Patient $patient): array
    {
        $paiements = $this->paiementRepo->createQueryBuilder('p')
            ->leftJoin('p.devis', 'd')->addSelect('d')
            ->leftJoin('d.fiche', 'f')->addSelect('f')
            ->leftJoin('f.patient', 'pat')->addSelect('pat')
            ->leftJoin('d.ficheMedicale', 'fm')->addSelect('fm')
            ->leftJoin('fm.patient', 'pm')->addSelect('pm')
            ->join('p.mode', 'm')->addSelect('m')
            ->where('pat = :patient OR pm = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(function (PaiementDevis $p) {
            $devis = $p->getDevis();

            if ($p->getConsultation()) {
                $patient = $p->getConsultation()->getPatient();
            } elseif ($p->getDevis() && $p->getDevis()->getFicheMedicale()?->getPatient()) {
                $patient = $p->getDevis()->getFicheMedicale()->getPatient();
            } elseif ($p->getDevis() && $p->getDevis()->getFiche()?->getPatient()) {
                $patient = $p->getDevis()->getFiche()->getPatient();
            } else {
                $patient = null;
            }

            return [
                'devisId' => $devis ? $devis->getId() : $p->getId(),
                'patient' => $patient ? $patient->getFullName() : 'Anonyme',
                'telephone' => $patient?->getTelephone(),
                'montant' => $p->getMontant(),
                'mode' => $p->getMode()->getLibelle(),
                'date' => $p->getDate()->format('Y-m-d H:i:s'),
                'type' => $devis ? 'devis' : 'ticket',
                'pId' => $p->getId(),
            ];
        }, $paiements);
    }

    public function previewDevis(int $id): ?array
    {
        $devis = $this->devisRepo->find($id);
        if (!$devis) {
            return null;
        }

        return $this->mapDevisToArray($devis);
    }

    public function previewFacture(int $id): ?array
    {
        $facture = $this->devisRepo->find($id);
        if (!$facture || !$facture->getConsultation()) {
            return null;
        }

        return $this->mapDevisToArray($facture, true);
    }

    private function mapDevisToArray(Devis $devis, bool $forceFacture = false): array
    {
        $fiche = $devis->getFiche();
        $ficheMedicale = $devis->getFicheMedicale();
        $consultation = $devis->getConsultation();
        $patient = $consultation?->getPatient()
            ?? $ficheMedicale?->getPatient()
            ?? $fiche?->getPatient();
        $contenus = $devis->getContenus();

        $contenuArr = array_map(function (ContenuDevis $c) {
            return [
                'designation' => $c->getDesignation(),
                'qte' => $c->getQte(),
                'montant' => $c->getMontant(),
                'total' => $c->getQte() * $c->getMontant(),
            ];
        }, $contenus->toArray());

        $isFacture = $forceFacture || $consultation !== null;

        return [
            'id' => $devis->getId(),
            'date' => $devis->getDate()?->format('Y-m-d') ?? (new \DateTime())->format('Y-m-d'),
            'consultation' => $consultation?->getId(),
            'montant' =>  $devis->getMontant(),
            'reste' => $isFacture ? $devis->getReste() : 0,
            'isRegle' => $devis->getStatut() == 1,
            'patient' => [
                'nom' => $patient?->getNom() ?? '',
                'prenom' => $patient?->getPrenom() ?? '',
                'telephone' => $patient?->getTelephone() ?? '',
            ],
            'contenus' => $contenuArr,
            'paiements' => $isFacture ? $this->buildDevisPaymentDetails($devis) : [],
            'type' => $isFacture ? 'Facture' : 'Devis',
            'insurance' => $isFacture ? $this->buildInsuranceMetadata($devis) : null,
        ];

        // $fiche = $devis->getFiche();
        // $patient = $fiche->getPatient();
        // $contenus = $devis->getContenus();

        // $contenuArr = array_map(function (ContenuDevis $c) {
        //     return [
        //         'designation' => $c->getDesignation(),
        //         'qte' => $c->getQte(),
        //         'montant' => $c->getMontant(),
        //         'total' => $c->getQte() * $c->getMontant(),
        //     ];
        // }, $contenus->toArray());

        // return [
        //     'id' => $devis->getId(),
        //     'date' => $devis->getDate()->format('Y-m-d'),
        //     'consultation' => $devis->getConsultation() ? $devis->getConsultation()->getId() : null,
        //     'montant' =>  $devis->getMontant(),
        //     'reste' => $devis->getStatut() === 1 ? $devis->getReste() : 0,
        //     'patient' => [
        //         'nom' => $patient->getNom(),
        //         'prenom' => $patient->getPrenom(),
        //         'telephone' => $patient->getTelephone(),
        //     ],
        //     'contenus' => $contenuArr,
        // ];
    }

    public function payerDevis(int $id, array $payload = []): array
    {
        $devis = $this->devisRepo->find($id);
        if (!$devis) {
            return ['error' => 'Devis introuvable'];
        }

        $modeId = (int) ($payload['modeId'] ?? 0);
        $montant = (float) ($payload['montant'] ?? 0);
        $date = $payload['date'] ?? null;
        $time = $payload['time'] ?? null;
        $insuranceEnabled = (bool) (($payload['insurance_enabled'] ?? $payload['insuranceEnabled'] ?? 0) == 1);
        $insuranceModeId = (int) ($payload['insurance_mode_id'] ?? $payload['insuranceModeId'] ?? 0);
        $insuranceRate = max(0, min(100, (float) ($payload['insurance_rate'] ?? $payload['insuranceRate'] ?? 0)));
        $insuranceAmountInput = (float) ($payload['insurance_amount'] ?? $payload['insuranceAmount'] ?? 0);
        $patientAmountInput = (float) ($payload['patient_amount'] ?? $payload['patientAmount'] ?? $montant);

        if ($devis->getReste() <= 0) {
            $devis->setStatut(1);
            $this->em->flush();
            return ['success' => true];
        }

        $timestamp = new \DateTime();
        if (!empty($date) && !empty($time)) {
            try {
                $timestamp = new \DateTime($date . ' ' . $time);
            } catch (\Exception) {
                $timestamp = new \DateTime();
            }
        }

        $createdPayment = null;
        $totalRemaining = (float) $devis->getReste();

        if ($insuranceEnabled) {
            if ($this->hasInsuranceAlreadyRecorded($devis)) {
                return ['error' => 'Une assurance est déjà enregistrée pour cette facture'];
            }

            $insuranceMode = $this->modeRepo->find($insuranceModeId);
            if (!$insuranceMode) {
                return ['error' => 'Données invalides'];
            }

            if ($insuranceRate <= 0) {
                $insuranceRate = max(0, min(100, (float) ($insuranceMode->getCoverageRate() ?? 0)));
            }

            $insuranceAmount = $insuranceAmountInput;
            if ($insuranceAmount <= 0 && $insuranceRate > 0) {
                $insuranceAmount = ($totalRemaining * $insuranceRate) / 100;
            }

            if ($insuranceAmount <= 0 && $patientAmountInput > 0 && $patientAmountInput < $totalRemaining) {
                $insuranceAmount = $totalRemaining - $patientAmountInput;
            }

            $insuranceAmount = max(0, min($totalRemaining, $insuranceAmount));

            $patientAmount = $patientAmountInput;
            if ($patientAmount <= 0) {
                $patientAmount = $totalRemaining - $insuranceAmount;
            }
            $patientAmount = max(0, min($totalRemaining - $insuranceAmount, $patientAmount));

            if (($patientAmount + $insuranceAmount) <= 0 || ($patientAmount + $insuranceAmount) > $totalRemaining) {
                return ['error' => 'Données invalides'];
            }

            if ($patientAmount > 0) {
                $modePatient = $this->modeRepo->find($modeId);
                if (!$modePatient) {
                    return ['error' => 'Données invalides'];
                }

                $paiementPatient = new PaiementDevis();
                $paiementPatient->setDevis($devis);
                $paiementPatient->setMode($modePatient);
                $paiementPatient->setMontant($patientAmount);
                $paiementPatient->setDate($timestamp);
                $paiementPatient->setRolePaiement('patient');

                $txPatient = new Transaction();
                $txPatient->setType('Entrée');
                $txPatient->setMontant($patientAmount);
                $txPatient->setDateTransaction($timestamp);
                $txPatient->setDescription('Paiement de la facture | Devis #' . $devis->getId() . ' | Part patient');
                $txPatient->setModeDePaiement($modePatient);
                $txPatient->setDevis($devis);
                $txPatient->setRolePaiement('patient');
                $txPatient->markValidated();
                $txPatient->setPaiementDevis($paiementPatient);

                $this->em->persist($txPatient);
                $this->em->persist($paiementPatient);
                $createdPayment = $paiementPatient;
            }

            if ($insuranceAmount > 0) {
                $txInsurance = new Transaction();
                $txInsurance->setType('Entrée');
                $txInsurance->setMontant($insuranceAmount);
                $txInsurance->setDateTransaction($timestamp);
                $txInsurance->setDescription('Paiement de la facture | Devis #' . $devis->getId() . ' | Part assurance');
                $txInsurance->setModeDePaiement($insuranceMode);
                $txInsurance->setDevis($devis);
                $txInsurance->setRolePaiement('insurance');
                $txInsurance->setTauxPriseEnCharge($insuranceRate > 0 ? $insuranceRate : null);
                $txInsurance->markPending();

                if ($this->globalSettingsService->isDirectInsurancePaymentEnabled()) {
                    $payInsurance = new PaiementDevis();
                    $payInsurance->setDevis($devis);
                    $payInsurance->setMode($insuranceMode);
                    $payInsurance->setMontant($insuranceAmount);
                    $payInsurance->setDate($timestamp);
                    $payInsurance->setRolePaiement('insurance');
                    $payInsurance->setTauxPriseEnCharge($insuranceRate > 0 ? $insuranceRate : null);
                    $txInsurance->setPaiementDevis($payInsurance);
                    $this->em->persist($payInsurance);
                    if ($createdPayment === null) {
                        $createdPayment = $payInsurance;
                    }
                }

                $this->em->persist($txInsurance);
            }

            $devis->setReste($devis->getReste() - ($patientAmount + $insuranceAmount));
        } else {
            $mode = $this->modeRepo->find($modeId);
            if (!$mode || $montant <= 0 || $montant > $devis->getReste()) {
                return ['error' => 'Données invalides'];
            }

            $paiement = new PaiementDevis();
            $paiement->setDevis($devis);
            $paiement->setMode($mode);
            $paiement->setMontant($montant);
            $paiement->setDate($timestamp);
            $paiement->setRolePaiement('patient');

            $devis->setReste($devis->getReste() - $montant);

            $transaction = new Transaction();
            $transaction->setType('Entrée');
            $transaction->setMontant($paiement->getMontant());
            $transaction->setDateTransaction($timestamp);
            $transaction->setDescription('Paiement de la facture | Devis #' . $devis->getId());
            $transaction->setModeDePaiement($mode);
            $transaction->setDevis($devis);
            $transaction->setRolePaiement('patient');
            $transaction->markValidated();
            $transaction->setPaiementDevis($paiement);

            $this->em->persist($transaction);
            $this->em->persist($paiement);
            $createdPayment = $paiement;
        }

        if ($devis->getReste() <= 0) {
            $devis->setReste(0);
            $devis->setStatut(1);
        }

        $this->em->persist($devis);
        $this->em->flush();
        $this->focusRealtimePublisher->publishDevisRefresh($devis, 'payment-updated');

        return ['success' => true, 'paiement_id' => $createdPayment?->getId()];
    }

    public function resetDevisPayments(int $id): array
    {
        $devis = $this->devisRepo->find($id);
        if (!$devis) {
            return ['error' => 'Devis introuvable'];
        }

        $allPaiements = $devis->getPaiements()->toArray();
        $paiementIds = array_values(array_filter(array_map(
            static fn (PaiementDevis $paiement): ?int => $paiement->getId(),
            $allPaiements
        )));

        $txQb = $this->transactionRepo->createQueryBuilder('t')
            ->where('t.devis = :devis')
            ->setParameter('devis', $devis);

        if (!empty($paiementIds)) {
            $txQb->orWhere('t.paiementDevis IN (:paiementIds)')
                ->setParameter('paiementIds', $paiementIds);
        }

        $transactions = $txQb->getQuery()->getResult();

        foreach ($transactions as $transaction) {
            $paiement = $transaction->getPaiementDevis();
            if ($paiement instanceof PaiementDevis && !in_array($paiement, $allPaiements, true)) {
                $allPaiements[] = $paiement;
            }

            $transaction->setPaiementDevis(null);
            $transaction->setDevis(null);
            $transaction->setConsultation(null);
            $this->em->remove($transaction);
            $this->em->flush();
        }

        foreach ($allPaiements as $paiement) {
            $paiement->setDevis(null);
            $paiement->setConsultation(null);
            $this->em->remove($paiement);
            $this->em->flush();
        }

        $devis->setReste((float) $devis->getMontant());
        $devis->setStatut(0);

        $this->em->persist($devis);
        $this->em->flush();
        $this->focusRealtimePublisher->publishDevisRefresh($devis, 'payments-reset');

        return ['success' => true];
    }

    public function paiementsForPeriod(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->paiementRepo->createQueryBuilder('p')
            ->leftJoin('p.devis', 'd')->addSelect('d')
            ->leftJoin('d.fiche', 'f')->addSelect('f')
            ->leftJoin('f.patient', 'pat')->addSelect('pat')
            ->leftJoin('d.ficheMedicale', 'fm')->addSelect('fm')
            ->leftJoin('fm.patient', 'pm')->addSelect('pm')
            ->join('p.mode', 'm')->addSelect('m')
            ->where('p.date BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function paiementById(int $id): ?PaiementDevis
    {
        return $this->paiementRepo->find($id);
    }

    public function getCaissePageContext(): array
    {
        return [
            'modesPaiement' => $this->modeRepo->findAll(),
        ];
    }
}
