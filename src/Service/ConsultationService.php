<?php

namespace App\Service;

use App\Entity\ActeMedical;
use App\Entity\Consultation;
use App\Entity\ContenuDevis;
use App\Entity\Devis;
use App\Entity\DocumentMedical;
use App\Entity\Employe;
use App\Entity\FicheObservation;
use App\Entity\Salle;
use App\Repository\ConsultationRepository;
use App\Repository\DevisRepository;
use App\Repository\EmployeRepository;
use App\Repository\SalleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ConsultationService
{
    private string $projectDir;

    public function __construct(
        private EntityManagerInterface $em,
        private DevisRepository $devisRepo,
        private ConsultationRepository $consultationRepo,
        private EmployeRepository $employeRepo,
        private SalleRepository $salleRepo,
        ParameterBagInterface $params,
    ) {
        $this->projectDir = $params->get('kernel.project_dir');
    }

    public function getMedecinForUser(?object $user): ?Employe
    {
        if (!$user) {
            return null;
        }

        return $this->employeRepo->findOneBy(['user' => $user]);
    }

    private function buildPendingConsultationsData(array $consultations): array
    {
        return array_map(function (Consultation $c) {
            $lastFiche = $c->getPatient()->getFichesObservation()->filter(fn($f) => $f !== null)->last();

            return [
                'id' => $c->getId(),
                'patient' => $c->getPatient()->getNom() . ' ' . $c->getPatient()->getPrenom(),
                'medecin' => $c->getMedecin() ? $c->getMedecin()->getFullName() : null,
                'dateDebut' => $c->getCreatedAt()->format('Y-m-d H:i'),
                'hasFiche' => $lastFiche ? true : false,
                'fiche' => $c->getFiche(),
            ];
        }, $consultations);
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
            $actesData[] = [
                'dent' => $a->getDent(),
                'type' => $a->getType(),
                'description' => $a->getDescription(),
                'prix' => $a->getPrix(),
                'quantite' => $a->getQuantite(),
            ];
        }

        return [
            'entity' => $c,
            'data' => [
                'id' => $c->getId(),
                'date' => $c->getCreatedAt()->format('Y-m-d H:i'),
                'patient' => $c->getPatient()->getNom() . ' ' . $c->getPatient()->getPrenom(),
                'medecin' => $c->getMedecin()?->getFullName(),
                'infirmier' => $c->getInfirmier()?->getNom(),
                'salle' => $c->getSalle()?->getNom(),
                'noteSeance' => $c->getNoteSeance(),
                'actes' => $actesData,
            ],
        ];
    }

    private function getFicheAndConsultation(int $ficheId, int $consultationId): array
    {
        $fiche = $this->em->getRepository(FicheObservation::class)->find($ficheId);
        if (!$fiche) {
            throw new NotFoundHttpException("FicheObservation {$ficheId} introuvable");
        }

        $consultation = $this->em->getRepository(Consultation::class)->find($consultationId);
        if (!$consultation || $consultation->getFiche() !== $fiche) {
            throw new NotFoundHttpException("Consultation {$consultationId} introuvable pour la fiche {$ficheId}");
        }

        return [$fiche, $consultation];
    }

    public function getConsultationJson(int $ficheId, int $consultationId): array
    {
        [$fiche, $consultation] = $this->getFicheAndConsultation($ficheId, $consultationId);

        $consultationData = [
            'id'         => $consultation->getId(),
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
            ];
        }

        return [
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

    public function updateMotif(int $ficheId, int $consultationId, array $data): void
    {
        [$fiche,] = $this->getFicheAndConsultation($ficheId, $consultationId);
        $fiche
            ->setMotif($data['motif'] ?? $fiche->getMotif())
            ->setHistoireMaladie($data['histoireMaladie'] ?? $fiche->getHistoireMaladie())
            ->setSoinsAnterieurs($data['soinsAnterieurs'] ?? $fiche->getSoinsAnterieurs());
        $this->em->flush();
    }

    public function updateExamens(int $ficheId, int $consultationId, array $data): void
    {
        [$fiche,] = $this->getFicheAndConsultation($ficheId, $consultationId);

        $fiche
            ->setExoInspection($data['exoInspection'] ?? '')
            ->setExoPalpation($data['exoPalpation'] ?? '')
            ->setEndoInspection($data['endoInspection'] ?? '')
            ->setEndoPalpation($data['endoPalpation'] ?? '')
            ->setOcclusion($data['occlusion'] ?? '')
            ->setExamenParodontal($data['examenParodontal'] ?? '')
            ->setDiagnostic($data['diagnostic'] ?? '');

        $toothsCheck = $fiche->getToothsCheck();
        if (isset($data['examensDentaires']) && is_array($data['examensDentaires'])) {
            foreach ($data['examensDentaires'] as $tooth => $result) {
                $toothsCheck[$tooth] = $result;
            }
        }
        $fiche->setToothsCheck($toothsCheck);
        $this->em->persist($fiche);
        $this->em->flush();
    }

    public function updateTraitements(int $ficheId, int $consultationId, array $data, array $files): void
    {
        [$fiche,] = $this->getFicheAndConsultation($ficheId, $consultationId);

        $fiche
            ->setTraitementUrgence($data['traitementUrgence'] ?? '')
            ->setTraitementDentaire($data['traitementDentaire'] ?? '')
            ->setTraitementParodontal($data['traitementParodontal'] ?? '')
            ->setTraitementOrthodontique($data['traitementOrthodontique'] ?? '')
            ->setAutres($data['autres'] ?? '');

        foreach ($fiche->getDocumentsMedicaux() as $d) {
            $this->em->remove($d);
        }

        $fs = new Filesystem();
        $uploadDir = $this->projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents';

        if (!$fs->exists($uploadDir)) {
            $fs->mkdir($uploadDir, 0775);
            $fs->chmod($uploadDir, 0775);
        }

        foreach ($files as $i => $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $libelle = $data['documents'][$i]['libelle'] ?? 'document';
            $dateDossier = new \DateTime($data['documents'][$i]['dateDossier'] ?? 'now');
            $description = $data['documents'][$i]['description'] ?? '';

            $patientNomComplet = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fiche->getPatient()->getNom() . '_' . $fiche->getPatient()->getPrenom());
            $libelleSanitized  = preg_replace('/[^a-zA-Z0-9_-]/', '_', $libelle);
            $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();
            $baseName = $libelleSanitized . '_' . $patientNomComplet;
            $filename = $baseName . '.' . $extension;

            $counter = 1;
            while (file_exists($uploadDir . '/' . $filename)) {
                $filename = $baseName . '_' . $counter . '.' . $extension;
                $counter++;
            }

            $movedFile = $file->move($uploadDir, $filename);

            $dm = new DocumentMedical();
            $dm->setFiche($fiche)
                ->setLibelle($libelle)
                ->setDateDossier($dateDossier)
                ->setDescription($description)
                ->setFichier('uploads/documents/' . $movedFile->getFilename());

            $this->em->persist($dm);
        }

        if (isset($data['documents']) && is_array($data['documents'])) {
            foreach ($data['documents'] as $i => $docData) {
                if (isset($files[$i]) && $files[$i] instanceof UploadedFile) {
                    continue;
                }

                if (!empty($docData['url'])) {
                    $dm = new DocumentMedical();
                    $dm->setFiche($fiche)
                        ->setLibelle($docData['libelle'] ?? '')
                        ->setDateDossier(new \DateTime($docData['dateDossier'] ?? 'now'))
                        ->setDescription($docData['description'] ?? '')
                        ->setFichier($docData['url']);

                    $this->em->persist($dm);
                }
            }
        }

        $this->em->flush();
    }

    public function updateDevis(int $ficheId, int $consultationId, array $data): void
    {
        [$fiche,] = $this->getFicheAndConsultation($ficheId, $consultationId);

        $oldDevis = $this->devisRepo->findOneBy(['fiche' => $fiche, 'type' => 0]);
        $devis = $oldDevis ?? new Devis();
        $devis->setFiche($fiche)
            ->setDate(new \DateTime($data['date'] ?? 'now'))
            ->setType(0)
            ->setStatut(0)
            ->setMontant(0);
        $this->em->persist($devis);

        foreach ($devis->getContenus() as $contenu) {
            $devis->removeContenu($contenu);
            $this->em->remove($contenu);
        }

        $amount = 0;
        if (isset($data['contenus']) && is_array($data['contenus'])) {
            foreach ($data['contenus'] as $c) {
                $cd = new ContenuDevis();
                $cd->setDevis($devis)
                    ->setDesignation($c['designation'] ?? '')
                    ->setQte($c['qte'] ?? 1)
                    ->setMontant($c['montant'] ?? 0);
                $amount += $cd->getMontant() * $cd->getQte();
                $cd->setMontantTotal($amount);
                $this->em->persist($cd);
            }
        }
        $devis->setMontant($amount);
        $this->em->persist($devis);
        $this->em->flush();
    }

    public function updateConsultation(int $ficheId, int $consultationId, array $data): void
    {
        [, $consultation] = $this->getFicheAndConsultation($ficheId, $consultationId);

        if (!empty($data['medecinId'])) {
            $consultation->setMedecin($this->em->getReference(Employe::class, $data['medecinId']));
        }
        if (!empty($data['infirmierId'])) {
            $consultation->setInfirmier($this->em->getReference(Employe::class, $data['infirmierId']));
        }
        if (!empty($data['salleId'])) {
            $consultation->setSalle($this->em->getReference(Salle::class, $data['salleId']));
        }
        $consultation->setNoteSeance($data['noteSeance'] ?? '');

        foreach ($consultation->getActes() as $a) {
            $this->em->remove($a);
        }
        if (isset($data['actes']) && is_array($data['actes'])) {
            foreach ($data['actes'] as $a) {
                $act = new ActeMedical();
                $act->setConsultation($consultation)
                    ->setDent($a['dent'] ?? '')
                    ->setType($a['type'] ?? '')
                    ->setDescription($a['description'] ?? '')
                    ->setPrix($a['prix'] ?? 0)
                    ->setQuantite($a['quantite'] ?? 1);
                $this->em->persist($act);
            }
        }

        $this->em->flush();
    }

    public function clotureConsultation(int $ficheId, int $consultationId): void
    {
        [$fiche, $consultation] = $this->getFicheAndConsultation($ficheId, $consultationId);

        $facture = new Devis();
        $facture->setFiche($fiche)
            ->setDate(new \DateTime('now'))
            ->setType(1)
            ->setStatut(0)
            ->setMontant(0);
        $this->em->persist($facture);

        foreach ($facture->getContenus() as $contenu) {
            $facture->removeContenu($contenu);
            $this->em->remove($contenu);
        }

        $amount = 0;
        foreach ($consultation->getActes() as $a) {
            $cd = new ContenuDevis();
            $cd->setDevis($facture)
                ->setDesignation($a->getType() ?? '')
                ->setQte($a->getQuantite() ?? 1)
                ->setMontant($a->getPrix() ?? 0);
            $amount += $cd->getMontant() * $cd->getQte();
            $cd->setMontantTotal($amount);
            $this->em->persist($cd);
        }

        $facture->setMontant($amount);
        $facture->setReste($amount);
        $facture->setConsultation($consultation);
        $this->em->persist($facture);
        $this->em->flush();

        $consultation->setStatut(1);
        $this->em->flush();
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
                'factstate' => $facture ? ($facture->getStatut() == 0 && (int) $facture->getMontant() === (int) $facture->getReste() ? 0 : 1) : null,
                'patientId' => $consultation->getPatient()?->getId(),
            ];
        }, $consultations);
    }

    public function listMedecins(): array
    {
        $employees = $this->employeRepo->FindAllMedecin();

        return array_map(function ($employee) {
            return [
                'id' => $employee->getId(),
                'nom' => $employee->getNom(),
                'prenom' => $employee->getPrenom(),
                'fonction' => $employee->getFonction(),
                'type' => $employee->getType(),
                'dateEmbauche' => $employee->getDateEmbauche()->format('Y-m-d'),
                'comingDays' => $employee->getComingDaysInWeek(),
            ];
        }, $employees);
    }

    public function getPendingConsultationsContext(): array
    {
        $consultations = $this->consultationRepo->findPendingConsultations();

        $consultationsData = array_map(function (Consultation $c) {
            $lastFiche = $c->getPatient()
                ->getFichesObservation()
                ->filter(fn($f) => $f !== null)
                ->last();

            return [
                'id' => $c->getId(),
                'patient' => $c->getPatient()->getNom() . ' ' . $c->getPatient()->getPrenom(),
                'medecin' => $c->getMedecin() ? $c->getMedecin()->getNom() : null,
                'dateDebut' => $c->getCreatedAt()->format('Y-m-d H:i'),
                'hasFiche' => $lastFiche ? true : false,
                'fiche' => $c->getFiche(),
            ];
        }, $consultations);

        return [
            'consultations' => $consultations,
            'consultationsData' => $consultationsData,
        ];
    }

    public function listPendingConsultationsJson(): array
    {
        $consults = $this->consultationRepo->findBy(['state' => 0]);

        return array_map(fn($c) => [
            'id' => $c->getId(),
            'patient' => $c->getPatient()->getNom() . ' ' . $c->getPatient()->getPrenom(),
            'medecin' => $c->getMedecin()->getNom(),
            'dateDebut' => $c->getCreatedAt()->format('Y-m-d H:i'),
            'hasfiche' => $c->getPatient()->getFichesObservation()->filter(fn($f) => $f !== null)->last() ? true : false,
            'fiche' => $c->getFiche(),
        ], $consults);
    }

    public function getEditConsultationContext(int $id, bool $createNewFiche = false): array
    {
        $consultation = $this->consultationRepo->find($id);

        if (!$consultation) {
            throw new NotFoundHttpException('Consultation non trouvée.');
        }

        if ($createNewFiche) {
            $fiche = new FicheObservation();
            $fiche->setPatient($consultation->getPatient());
            $this->em->persist($fiche);
            $consultation->setFiche($fiche);
        } else {
            $fiche = $consultation->getPatient()->getFichesObservation()
                ->filter(fn($f) => $f !== null)
                ->last();
            if (!$consultation->getFiche()) {
                $consultation->setFiche($fiche);
            }
        }

        $this->em->persist($consultation);
        $this->em->flush();

        $medecins = $this->employeRepo->findBy(['type' => 'medecin']);
        $infirmiers = $this->employeRepo->findBy(['type' => 'infirmier']);
        $salles = $this->salleRepo->findAll();

        return [
            'consultation' => $consultation,
            'fiche' => $consultation->getFiche() ?: $fiche,
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

    
    public function deleteConsultation(int $id): bool
    {
        $consultation = $this->consultationRepo->find($id);

        if (!$consultation) {
            return false;
        }

        if ($consultation->getPaiementDevis()) {
            $paiementDevis = $consultation->getPaiementDevis();
            $transaction = $paiementDevis->getTransaction();

            $paiementDevis->setTransaction(null);
            $paiementDevis->setConsultation(null);
            $consultation->setPaiementDevis(null);

            $this->em->flush();

            if ($transaction) {
                $this->em->remove($transaction);
            }
            $this->em->remove($paiementDevis);
            $this->em->remove($consultation);

            $this->em->flush();
        } else {
            $this->em->remove($consultation);
            $this->em->flush();
        }

        return true;
    }

    public function getFactureLines(Consultation $consultation): ?array
    {
        $devis = $consultation->getFacture();

        if (!$devis) {
            return null;
        }

        $lignes = [];
        foreach ($devis->getContenus() as $contenu) {
            $lignes[] = [
                'id' => $contenu->getId(),
                'designation' => $contenu->getDesignation(),
                'quantite' => $contenu->getQte(),
                'montant' => $contenu->getMontant(),
            ];
        }

        return $lignes;
    }

    public function updateFactureLines(Consultation $consultation, array $lignes): array
    {
        if (!is_array($lignes)) {
            return ['error' => 'Payload invalide'];
        }

        $devis = $consultation->getFacture();

        if (!$devis) {
            return ['error' => 'Facture non trouvée'];
        }

        foreach ($devis->getContenus() as $old) {
            $this->em->remove($old);
        }
        $this->em->flush();

        $total = 0;
        foreach ($lignes as $ligneData) {
            $cd = new ContenuDevis();
            $cd->setDevis($devis)
                ->setQte((int) ($ligneData['quantite'] ?? 1))
                ->setMontant((float) ($ligneData['montant'] ?? $ligneData['prix'] ?? 0))
                ->setDesignation($ligneData['designation'] ?? $ligneData['description'] ?? '')
                ->setMontantTotal((int) ($ligneData['quantite'] ?? 1) * (float) ($ligneData['montant'] ?? $ligneData['prix'] ?? 0));

            $total += $cd->getMontant() * $cd->getQte();
            $this->em->persist($cd);
        }

        $devis->setMontant($total)
            ->setReste($total);

        $this->em->persist($devis);
        $this->em->flush();

        return ['success' => true];
    }

    public function consultationsDuJour(?string $dateStr, $user): array
    {
        $date = $dateStr ? (\DateTime::createFromFormat('Y-m-d', $dateStr) ?: new \DateTime()) : new \DateTime();

        $start = (clone $date)->setTime(0, 0, 0);
        $end = (clone $date)->setTime(23, 59, 59);

        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Consultation::class, 'c')
            ->join('c.patient', 'p')
            ->join('c.medecin', 'm')
            ->where('c.CreatedAt BETWEEN :start AND :end')
            ->orderBy('c.CreatedAt', 'ASC')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($user && in_array('ROLE_MEDECIN', $user->getRoles())) {
            $medecin = $this->employeRepo->FindOneBy(['user' => $user]);
            if ($medecin) {
                $qb->andWhere('m = :medecin')
                    ->setParameter('medecin', $medecin);
            }
        }

        $consultations = $qb->getQuery()->getResult();

        $data = [];
        $counter = 1;
        foreach ($consultations as $c) {
            $data[] = [
                'id' => $c->getId(),
                'numero' => $counter++,
                'patient' => $c->getPatient()->getFullName(),
                'patientId' => $c->getPatient()->getId(),
                'medecin' => $c->getMedecin()->getFullName(),
                'createdAt' => $c->getCreatedAt()->format('d/m/Y H:i'),
                'factstate' => $c->getFacture() ? ($c->getFacture()?->getStatut() == 0 && (int) $c->getFacture()->getMontant() === (int) $c->getFacture()->getReste() ? 0 : 1) : null,
                'state' => $c->getStatut(),
            ];
        }

        return ['data' => $data];
    }
}
