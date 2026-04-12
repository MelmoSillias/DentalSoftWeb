<?php

namespace App\Service;

use App\Entity\ContenuDevis;
use App\Entity\Devis;
use App\Entity\PaiementDevis;
use App\Entity\Patient;
use App\Entity\Transaction;
use App\Repository\DevisRepository;
use App\Repository\ModeDePaiementRepository;
use App\Repository\PaiementDevisRepository;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

class CashdeskService
{
    public function __construct(
        private DevisRepository $devisRepo,
        private PaiementDevisRepository $paiementRepo,
        private ModeDePaiementRepository $modeRepo,
        private EntityManagerInterface $em,
        private GlobalSettingsService $globalSettingsService,
    ) {
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
            $patient = $d->getFicheMedicale()?->getPatient() ?? $d->getFiche()?->getPatient();

            return [
                'id' => $d->getId(),
                'date' => $d->getDate()->format('Y-m-d'),
                'consultation' => $d->getConsultation()?->getId(),
                'montant' => $d->getMontant(),
                'reste' => $d->getReste(),
                'statut' => $d->getStatut(),
                'isRegle' => $d->getStatut() == 1,
                'patient' => [
                    'nom' => $patient?->getNom() ?? '',
                    'prenom' => $patient?->getPrenom() ?? '',
                ],
                'telephone' => $patient?->getTelephone(),
            ];
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
            $patient = $d->getFicheMedicale()?->getPatient() ?? $d->getFiche()?->getPatient();

            return [
                'id' => $d->getId(),
                'date' => $d->getDate()->format('Y-m-d'),
                'consultation' => $d->getConsultation()->getId(),
                'montant' => $d->getMontant(),
                'reste' => $d->getReste(),
                'isRegle' => $d->getStatut() == 1,
                'patient' => [
                    'nom' => $patient?->getNom() ?? '',
                    'prenom' => $patient?->getPrenom() ?? '',
                ],
                'telephone' => $patient?->getTelephone(),
            ];
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
            $patient = $d->getFicheMedicale()?->getPatient() ?? $d->getFiche()?->getPatient();

            return [
                'id' => $d->getId(),
                'date' => $d->getDate()->format('Y-m-d'),
                'consultation' => $d->getConsultation()->getId(),
                'montant' => $d->getMontant(),
                'reste' => $d->getReste(),
                'isRegle' => $d->getStatut() == 1,
                'patient' => [
                    'nom' => $patient?->getNom() ?? '',
                    'prenom' => $patient?->getPrenom() ?? '',
                ],
                'telephone' => $patient?->getTelephone(),
            ];
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
            $patient = $d->getFicheMedicale()?->getPatient() ?? $d->getFiche()?->getPatient();

            return [
                'id' => $d->getId(),
                'date' => $d->getDate()->format('Y-m-d'),
                'consultation' => $d->getConsultation()->getId(),
                'montant' => $d->getMontant(),
                'reste' => $d->getReste(),
                'isRegle' => $d->getStatut() == 1,
                'patient' => [
                    'nom' => $patient?->getNom() ?? '',
                    'prenom' => $patient?->getPrenom() ?? '',
                ],
                'telephone' => $patient?->getTelephone(),
            ];
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
                'date' => $p->getDate()->format('Y-m-d H:i:s'),
                'type' => $devis ? 'devis' : 'ticket',
                'pId' => $p->getId(),
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
            'patient' => [
                'nom' => $patient?->getNom() ?? '',
                'prenom' => $patient?->getPrenom() ?? '',
                'telephone' => $patient?->getTelephone() ?? '',
            ],
            'contenus' => $contenuArr,
            'type' => $isFacture ? 'Facture' : 'Devis',
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

            $devis->setReste($devis->getReste() - $montant);

            $transaction = new Transaction();
            $transaction->setType('Entrée');
            $transaction->setMontant($paiement->getMontant());
            $transaction->setDateTransaction($timestamp);
            $transaction->setDescription('Paiement de la facture | Devis #' . $devis->getId());
            $transaction->setModeDePaiement($mode);
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

        return ['success' => true, 'paiement_id' => $createdPayment?->getId()];
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
