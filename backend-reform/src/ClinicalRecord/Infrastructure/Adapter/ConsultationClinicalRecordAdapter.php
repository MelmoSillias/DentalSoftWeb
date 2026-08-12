<?php

namespace App\ClinicalRecord\Infrastructure\Adapter;

use App\CareDelivery\Application\Port\ConsultationClinicalRecordPort;
use App\CareDelivery\Infrastructure\Persistence\Doctrine\Entity\Consultation;
use App\ClinicalRecord\Infrastructure\Persistence\Doctrine\Entity\FicheMedicale;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Allergy;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Antecedent;
use App\Patient\Infrastructure\Persistence\Doctrine\Entity\Patient;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ConsultationClinicalRecordAdapter implements ConsultationClinicalRecordPort
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getFicheById(int $ficheId): object
    {
        $ficheMed = $this->em->getRepository(FicheMedicale::class)->find($ficheId);
        if ($ficheMed) {
            return $ficheMed;
        }

        throw new NotFoundHttpException("Fiche {$ficheId} introuvable");
    }

    public function findLastFicheForPatient(object $patient): ?object
    {
        if (!$patient instanceof Patient || !$patient->getId()) {
            return null;
        }

        return $this->em->getRepository(FicheMedicale::class)
            ->createQueryBuilder('f')
            ->andWhere('f.patient = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('f.createdAt', 'DESC')
            ->addOrderBy('f.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function resolvePendingFicheData(object $consultation): array
    {
        if (!$consultation instanceof Consultation) {
            return [
                'ficheMedicale' => null,
                'fiche' => null,
                'ficheId' => null,
                'hasFiche' => false,
                'lastFicheId' => null,
                'motif' => '',
            ];
        }

        $patient = $consultation->getPatient();
        $lastFicheMedicale = $patient ? $this->findLastFicheForPatient($patient) : null;

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

    public function resolveFicheForConsultation(
        object $consultation,
        ?int $ficheId = null,
        bool $forceCreate = false,
        bool $allowDuplicate = false,
    ): array {
        if (!$consultation instanceof Consultation) {
            throw new \InvalidArgumentException('Consultation invalide.');
        }

        $patient = $consultation->getPatient();
        if (!$patient) {
            throw new \InvalidArgumentException('Consultation sans patient.');
        }

        $existingFiche = $consultation->getFicheMedicale();
        if (!$forceCreate && $existingFiche) {
            if ($ficheId === null || $ficheId === $existingFiche->getId()) {
                return [$existingFiche, false];
            }
        }

        if ($forceCreate) {
            if (!$allowDuplicate) {
                $lastFiche = $this->findLastFicheForPatient($patient);
                if ($lastFiche instanceof FicheMedicale) {
                    $consultation->setFicheMedicale($lastFiche);

                    return [$lastFiche, false];
                }
            }

            $fiche = new FicheMedicale();
            $fiche->setPatient($patient);
            $this->em->persist($fiche);
            $consultation->setFicheMedicale($fiche);

            return [$fiche, true];
        }

        if ($ficheId) {
            $ficheMedicale = $this->em->getRepository(FicheMedicale::class)->find($ficheId);
            if (!$ficheMedicale) {
                throw new NotFoundHttpException('Fiche introuvable');
            }

            if ($ficheMedicale->getPatient()?->getId() !== $patient->getId()) {
                throw new \InvalidArgumentException('La fiche ne correspond pas au patient de la consultation.');
            }

            $consultation->setFicheMedicale($ficheMedicale);

            return [$ficheMedicale, false];
        }

        if ($existingFiche) {
            return [$existingFiche, false];
        }

        $lastFiche = $this->findLastFicheForPatient($patient);
        if ($lastFiche instanceof FicheMedicale) {
            $consultation->setFicheMedicale($lastFiche);

            return [$lastFiche, false];
        }

        $fiche = new FicheMedicale();
        $fiche->setPatient($patient);
        $this->em->persist($fiche);
        $consultation->setFicheMedicale($fiche);

        return [$fiche, true];
    }

    public function lockPatientForFicheResolution(object $patient): void
    {
        if (!$patient instanceof Patient || !$patient->getId()) {
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

    public function getFicheConsultationPayload(object $fiche, object $consultation): array
    {
        if (!$fiche instanceof FicheMedicale || !$consultation instanceof Consultation) {
            throw new \InvalidArgumentException('Fiche ou consultation invalide.');
        }

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
            'id' => $patient->getId(),
            'nom' => $patient->getNom(),
            'prenom' => $patient->getPrenom(),
            'age' => $patient->getDateNaissance() ? $patient->getDateNaissance()->format('Y-m-d') : null,
            'sexe' => $patient->getSexe(),
            'telephone' => $patient->getTelephone(),
            'allergies' => $allergies,
            'antecedents' => $antecedents,
        ];

        $consultationData = [
            'id' => $consultation->getId(),
            'type' => $consultation->getType(),
            'noteSeance' => $consultation->getNoteSeance(),
            'medecin' => $consultation->getMedecin() ? ['id' => $consultation->getMedecin()->getId(), 'name' => $consultation->getMedecin()->getFullName()] : null,
            'infirmier' => $consultation->getInfirmier() ? ['id' => $consultation->getInfirmier()->getId(), 'name' => $consultation->getInfirmier()->getFullName()] : null,
            'salle' => $consultation->getSalle() ? ['id' => $consultation->getSalle()->getId(), 'name' => $consultation->getSalle()->getNom()] : null,
        ];

        $ficheData = [
            'id' => $fiche->getId(),
            'motif' => $fiche->getMotif(),
            'histoireMaladie' => $fiche->getHistoireMaladie(),
            'soinsAnterieurs' => $fiche->getSoinsAnterieurs(),
            'exoInspection' => $fiche->getExoInspection(),
            'exoPalpation' => $fiche->getExoPalpation(),
            'endoInspection' => $fiche->getEndoInspection(),
            'endoPalpation' => $fiche->getEndoPalpation(),
            'occlusion' => $fiche->getOcclusion(),
            'examenParodontal' => $fiche->getExamenParodontal(),
            'diagnostic' => $fiche->getDiagnostic(),
            'examensComplementaires' => $fiche->getExamensComplementaires(),
            'diagnosticSupposeExamens' => $fiche->getDiagnosticSupposeExamens(),
            'traitementUrgence' => $fiche->getTraitementUrgence(),
            'traitementDentaire' => $fiche->getTraitementDentaire(),
            'traitementParodontal' => $fiche->getTraitementParodontal(),
            'traitementOrthodontique' => $fiche->getTraitementOrthodontique(),
            'autres' => $fiche->getAutres(),
        ];

        $examens = $fiche->getToothsCheck();

        $documents = [];
        foreach ($fiche->getDocumentsMedicaux() as $d) {
            $documents[] = [
                'libelle' => $d->getLibelle(),
                'dateDossier' => $d->getDateDossier()->format('Y-m-d'),
                'description' => $d->getDescription(),
                'url' => $d->getFichier(),
            ];
        }

        $devis = $fiche->getDevis()[0] ?? null;
        $devisData = null;
        if ($devis) {
            $contenus = [];
            foreach ($devis->getContenus() as $c) {
                $contenus[] = [
                    'designation' => $c->getDesignation(),
                    'qte' => $c->getQte(),
                    'montant' => $c->getMontant(),
                ];
            }
            $devisData = [
                'id' => $devis->getId(),
                'date' => $devis->getDate()->format('Y-m-d'),
                'contenus' => $contenus,
            ];
        }

        $precedentes = [];
        foreach ($fiche->getConsultations() as $s) {
            if ($s->getId() !== $consultation->getId() && $s->getStatut() === 1) {
                $precedentes[] = [
                    'id' => $s->getId(),
                    'date' => $s->getCreatedAt()->format('Y-m-d'),
                    'medecin' => $s->getMedecin() ? $s->getMedecin()->getFullName() : null,
                    'infirmier' => $s->getInfirmier() ? $s->getInfirmier()->getFullName() : null,
                    'salle' => $s->getSalle() ? $s->getSalle()->getNom() : null,
                    'noteSeance' => $s->getNoteSeance(),
                ];
            }
        }

        $actes = [];
        foreach ($consultation->getActes() as $a) {
            $actes[] = [
                'dent' => $a->getDent(),
                'type' => $a->getType(),
                'description' => $a->getDescription(),
                'prix' => $a->getPrix(),
                'quantite' => $a->getQuantite(),
            ];
        }

        return [
            'patient' => $patientData,
            'consultation' => $consultationData,
            'fiche' => array_merge($ficheData, [
                'examens' => $examens,
                'documents' => $documents,
                'devis' => $devisData,
                'consultations' => $precedentes,
            ]),
            'actes' => $actes,
        ];
    }
}
