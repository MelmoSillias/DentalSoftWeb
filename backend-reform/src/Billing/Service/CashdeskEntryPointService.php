<?php

namespace App\Billing\Service;

use App\Billing\Dto\CashdeskFactureListDto;
use App\Billing\Entity\Paiement;
use App\Billing\Repository\ModeDePaiementRepository;
use App\Billing\Repository\PaiementRepository;
use App\Billing\Service\Workflow\ClassicInvoiceWorkflowService;
use App\Billing\Service\Workflow\InsuredInvoiceWorkflowService;
use App\Patient\Entity\Patient;
use DateTimeInterface;

class CashdeskEntryPointService
{
    public function __construct(
        private ClassicInvoiceWorkflowService $classicWorkflow,
        private InsuredInvoiceWorkflowService $insuredWorkflow,
        private PaiementRepository $paiementRepo,
        private ModeDePaiementRepository $modeRepo,
    ) {
    }

    public function getClassicWorkflow(): ClassicInvoiceWorkflowService
    {
        return $this->classicWorkflow;
    }

    public function getInsuredWorkflow(): InsuredInvoiceWorkflowService
    {
        return $this->insuredWorkflow;
    }

    // ── Unified listing ────────────────────────────────────────────────

    public function listAllFactures(DateTimeInterface $start, DateTimeInterface $end): CashdeskFactureListDto
    {
        $classiques = $this->classicWorkflow->listFacturesByPeriod($start, $end);
        $assurances = $this->insuredWorkflow->listFacturesAssuranceForCashdesk($start, $end);

        return new CashdeskFactureListDto($classiques, $assurances);
    }

    // ── Cross-workflow payment listings ────────────────────────────────

    public function listPaiementsFactures(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $paiements = $this->paiementRepo->createQueryBuilder('p')
            ->leftJoin('p.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'pat')->addSelect('pat')
            ->leftJoin('p.facture', 'f')->addSelect('f')
            ->leftJoin('f.consultation', 'fc')->addSelect('fc')
            ->leftJoin('fc.patient', 'fpat')->addSelect('fpat')
            ->leftJoin('p.factureAssurance', 'fa')->addSelect('fa')
            ->leftJoin('fa.consultation', 'fac')->addSelect('fac')
            ->leftJoin('fac.patient', 'fapat')->addSelect('fapat')
            ->join('p.mode', 'm')->addSelect('m')
            ->where('p.date BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(function (Paiement $p) {
            $patient = $p->getConsultation()?->getPatient()
                ?? $p->getFacture()?->getConsultation()?->getPatient()
                ?? $p->getFactureAssurance()?->getPatient();
            $factureId = $p->getFacture()?->getId() ?? $p->getFactureAssurance()?->getId();
            $consultationId = $p->getFacture()?->getConsultation()?->getId()
                ?? $p->getFactureAssurance()?->getConsultation()?->getId()
                ?? $p->getConsultation()?->getId();
            $type = $p->getFacture() ? 'facture' : ($p->getFactureAssurance() ? 'facture_assurance' : 'ticket');

            return [
                'factureId' => $factureId ?? $p->getId(),
                'consultationId' => $consultationId,
                'patient' => $patient ? $patient->getFullName() : 'Anonyme',
                'telephone' => $patient?->getTelephone(),
                'montant' => $p->getMontant(),
                'mode' => $p->getMode()->getLibelle(),
                'modeId' => $p->getMode()->getId(),
                'date' => $p->getDate()->format('Y-m-d H:i:s'),
                'type' => $type,
                'pId' => $p->getId(),
            ];
        }, $paiements);
    }

    public function listPaiementsByPatients(Patient $patient): array
    {
        $paiements = $this->paiementRepo->createQueryBuilder('p')
            ->leftJoin('p.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'pat')->addSelect('pat')
            ->leftJoin('p.facture', 'f')->addSelect('f')
            ->leftJoin('f.consultation', 'fc')->addSelect('fc')
            ->leftJoin('fc.patient', 'fpat')->addSelect('fpat')
            ->leftJoin('p.factureAssurance', 'fa')->addSelect('fa')
            ->leftJoin('fa.patient', 'fapat')->addSelect('fapat')
            ->join('p.mode', 'm')->addSelect('m')
            ->where('pat = :patient OR fpat = :patient OR fapat = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(function (Paiement $p) {
            $resolvedPatient = $p->getConsultation()?->getPatient()
                ?? $p->getFacture()?->getConsultation()?->getPatient()
                ?? $p->getFactureAssurance()?->getPatient();
            $factureId = $p->getFacture()?->getId() ?? $p->getFactureAssurance()?->getId();
            $consultationId = $p->getFacture()?->getConsultation()?->getId()
                ?? $p->getFactureAssurance()?->getConsultation()?->getId()
                ?? $p->getConsultation()?->getId();
            $type = $p->getFacture() ? 'facture' : ($p->getFactureAssurance() ? 'facture_assurance' : 'ticket');

            return [
                'factureId' => $factureId ?? $p->getId(),
                'consultationId' => $consultationId,
                'patient' => $resolvedPatient ? $resolvedPatient->getFullName() : 'Anonyme',
                'telephone' => $resolvedPatient?->getTelephone(),
                'montant' => $p->getMontant(),
                'mode' => $p->getMode()->getLibelle(),
                'date' => $p->getDate()->format('Y-m-d H:i:s'),
                'type' => $type,
                'pId' => $p->getId(),
            ];
        }, $paiements);
    }

    // ── Shared utilities ───────────────────────────────────────────────

    public function paiementsForPeriod(DateTimeInterface $start, DateTimeInterface $end): array
    {
        return $this->paiementRepo->createQueryBuilder('p')
            ->leftJoin('p.consultation', 'c')->addSelect('c')
            ->leftJoin('c.patient', 'pat')->addSelect('pat')
            ->join('p.mode', 'm')->addSelect('m')
            ->where('p.date BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function paiementById(int $id): ?Paiement
    {
        return $this->paiementRepo->find($id);
    }

    public function getCaissePageContext(): array
    {
        return [
            'modesPaiement' => $this->modeRepo->findAll(),
        ];
    }

    public function mapPaiementReceipt(Paiement $paiement): array
    {
        $patient = $this->resolvePatientFromPaiement($paiement);
        $factureId = $paiement->getFacture()?->getId();

        return [
            'id' => $paiement->getId(),
            'date' => $paiement->getDate()?->format('Y-m-d H:i'),
            'montant' => $paiement->getMontant(),
            'mode' => [
                'libelle' => $paiement->getMode()?->getLibelle(),
            ],
            'devis' => $factureId ? [
                'id' => $factureId,
                'total' => $paiement->getFacture()->computeMontantsFromConsultation()['montantTotal'] ?? 0.0,
                'reste' => $paiement->getFacture()->computeMontantsFromConsultation()['restePatient'] ?? 0.0,
                'fiche' => [
                    'patient' => $patient ? [
                        'nom' => $patient->getNom(),
                        'prenom' => $patient->getPrenom(),
                    ] : null,
                ],
            ] : null,
        ];
    }

    public function mapPaiementTicket(Paiement $paiement): array
    {
        $consultation = $paiement->getConsultation();
        $patient = $consultation?->getPatient();

        return [
            'id' => $paiement->getId(),
            'date' => $paiement->getDate()?->format('Y-m-d H:i'),
            'montant' => $paiement->getMontant(),
            'mode' => [
                'libelle' => $paiement->getMode()?->getLibelle(),
            ],
            'consultation' => $consultation ? [
                'patient' => $patient ? [
                    'nom' => $patient->getNom(),
                    'prenom' => $patient->getPrenom(),
                ] : null,
            ] : null,
        ];
    }

    public function mapPaiementListItem(Paiement $paiement): array
    {
        $patient = $this->resolvePatientFromPaiement($paiement);
        $factureId = $paiement->getFacture()?->getId();

        return [
            'facture' => $factureId ? [
                'id' => $factureId,
                'fiche' => [
                    'patient' => $patient ? [
                        'nom' => $patient->getNom(),
                        'prenom' => $patient->getPrenom(),
                    ] : null,
                ],
            ] : null,
            'montant' => $paiement->getMontant(),
            'mode' => [
                'libelle' => $paiement->getMode()?->getLibelle(),
            ],
            'date' => $paiement->getDate()?->format('Y-m-d H:i'),
        ];
    }

    public function resolvePatientFromPaiement(?Paiement $paiement): ?Patient
    {
        if (!$paiement instanceof Paiement) {
            return null;
        }

        $facture = $paiement->getFacture();
        $fromFicheMedicale = $facture?->getConsultation()?->getFicheMedicale()?->getPatient();
        if ($fromFicheMedicale instanceof Patient) {
            return $fromFicheMedicale;
        }

        $fiche = $facture?->getConsultation()?->getFicheMedicale();
        if ($fiche && method_exists($fiche, 'getPatient')) {
            $patient = $fiche->getPatient();
            if ($patient instanceof Patient) {
                return $patient;
            }
        }

        return $paiement->getConsultation()?->getPatient()
            ?? $paiement->getFacture()?->getConsultation()?->getPatient()
            ?? $paiement->getFactureAssurance()?->getPatient();
    }
}
