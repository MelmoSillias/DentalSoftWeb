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

        $fiche = $devis->getFiche();
        $patient = $fiche->getPatient();
        $contenus = $devis->getContenus();

        $contenuArr = array_map(function (ContenuDevis $c) {
            return [
                'designation' => $c->getDesignation(),
                'qte' => $c->getQte(),
                'montant' => $c->getMontant(),
                'total' => $c->getQte() * $c->getMontant(),
            ];
        }, $contenus->toArray());

        return [
            'id' => $devis->getId(),
            'date' => $devis->getDate()->format('Y-m-d'),
            'consultation' => $devis->getConsultation() ? $devis->getConsultation()->getId() : null,
            'montant' =>  $devis->getMontant(),
            'reste' => $devis->getStatut() === 1 ? $devis->getReste() : 0,
            'patient' => [
                'nom' => $patient->getNom(),
                'prenom' => $patient->getPrenom(),
                'telephone' => $patient->getTelephone(),
            ],
            'contenus' => $contenuArr,
        ];
    }

    public function payerDevis(int $id, int $modeId, float $montant, ?string $date = null, ?string $time = null): array
    {
        $devis = $this->devisRepo->find($id);
        if (!$devis) {
            return ['error' => 'Devis introuvable'];
        }

        if ($devis->getReste() <= 0) {
            $devis->setStatut(1);
            $this->em->flush();
            return ['success' => true];
        }

        $mode = $this->modeRepo->find($modeId);
        if (!$mode || $montant <= 0 || $montant > $devis->getReste()) {
            return ['error' => 'Données invalides'];
        }

        $paiement = new PaiementDevis();
        $paiement->setDevis($devis);
        $paiement->setMode($mode);
        $paiement->setMontant($montant);

        $timestamp = new \DateTime();
        if (!empty($date) && !empty($time)) {
            try {
                $timestamp = new \DateTime($date . ' ' . $time);
            } catch (\Exception) {
                $timestamp = new \DateTime();
            }
        }

        $paiement->setDate($timestamp);

        $devis->setReste($devis->getReste() - $montant);
        if ($devis->getReste() <= 0) {
            $devis->setReste(0);
            $devis->setStatut(1);
        }

        $transaction = new Transaction();
        $transaction->setType('Entrée');
        $transaction->setMontant($paiement->getMontant());
        $transaction->setDateTransaction($timestamp);
        $transaction->setDescription('Paiement de la facture | Devis #' . $devis->getId());
        $transaction->setModeDePaiement($mode);
        $transaction->setPaiementDevis($paiement);

        $this->em->persist($transaction);
        $this->em->persist($paiement);
        $this->em->persist($devis);
        $this->em->flush();

        return ['success' => true, 'paiement_id' => $paiement->getId()];
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
