<?php

namespace App\Controller\Admin;

use App\Entity\FicheObservation;
use App\Entity\Consultation;
use App\Entity\ActeMedical;
use App\Entity\ExamenDentaire;
use App\Entity\DocumentMedical;
use App\Entity\Devis;
use App\Entity\ContenuDevis;
use App\Entity\Employe;
use App\Entity\Salle;
use App\Repository\DevisRepository;
use App\Repository\FicheObservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/api/fiche/{ficheId}/consultation/{consultationId}', name: 'api_fiche_consultation_')]
class ConsultationApiController extends AbstractController
{
    private function getFicheAndConsultation(EntityManagerInterface $em, int $ficheId, int $consultationId): array
    {
        $fiche = $em->getRepository(FicheObservation::class)->find($ficheId);
        if (!$fiche) {
            throw new NotFoundHttpException("FicheObservation $ficheId introuvable");
        }

        $consultation = $em->getRepository(Consultation::class)->find($consultationId);
        if (!$consultation || $consultation->getFiche() !== $fiche) {
            throw new NotFoundHttpException("Consultation $consultationId introuvable pour la fiche $ficheId");
        }

        return [$fiche, $consultation];
    }

    #[Route('/json', name: 'json', methods: ['GET'])]
    public function getConsJson(EntityManagerInterface $em, int $ficheId, int $consultationId): JsonResponse
    {
        [$fiche, $consultation] = $this->getFicheAndConsultation($em, $ficheId, $consultationId);

        // --- Consultation ---
        $consultationData = [
            'id'         => $consultation->getId(),
            'noteSeance' => $consultation->getNoteSeance(),
            'medecin'    => $consultation->getMedecin()    ? ['id'=>$consultation->getMedecin()->getId(),'name'=>$consultation->getMedecin()->getFullName()] : null,
            'infirmier'  => $consultation->getInfirmier()  ? ['id'=>$consultation->getInfirmier()->getId(),'name'=>$consultation->getInfirmier()->getFullName()] : null,
            'salle'      => $consultation->getSalle()      ? ['id'=>$consultation->getSalle()->getId(),  'name'=>$consultation->getSalle()->getNom()]    : null,
        ];

        // --- Fiche d'observation ---
        $ficheData = [
            'id'                 => $fiche->getId(),
            'motif'              => $fiche->getMotif(),
            'histoireMaladie'    => $fiche->getHistoireMaladie(),
            'soinsAnterieurs'    => $fiche->getSoinsAnterieurs(),
            'exoInspection'      => $fiche->getExoInspection(),
            'exoPalpation'       => $fiche->getExoPalpation(),
            'endoInspection'     => $fiche->getEndoInspection(),
            'endoPalpation'      => $fiche->getEndoPalpation(),
            'occlusion'          => $fiche->getOcclusion(),
            'examenParodontal'   => $fiche->getExamenParodontal(),
            'diagnostic'         => $fiche->getDiagnostic(),
            'traitementUrgence'  => $fiche->getTraitementUrgence(),
            'traitementDentaire' => $fiche->getTraitementDentaire(),
            'traitementParodontal'=> $fiche->getTraitementParodontal(),
            'traitementOrthodontique'=> $fiche->getTraitementOrthodontique(),
            'autres'             => $fiche->getAutres(),
        ];

        // Examens dentaires
        $examens = $fiche->getToothsCheck();

        // Documents médicaux
        $documents = [];
        foreach ($fiche->getDocumentsMedicaux() as $d) {
            $documents[] = [
                'libelle'    => $d->getLibelle(),
                'dateDossier'=> $d->getDateDossier()->format('Y-m-d'),
                'description'=> $d->getDescription(),
                'url'        => $d->getFichier(),
            ];
        }

        // Devis
        $devis      = $fiche->getDevis();
        $devisData  = null;
        if ($devis) {
            $contenus = [];
            foreach ($devis->getContenus() as $c) {
                $contenus[] = [
                    'designation'=> $c->getDesignation(),
                    'qte'        => $c->getQte(),
                    'montant'    => $c->getMontant(),
                ];
            }
            $devisData = [
                'date'     => $devis->getDate()->format('Y-m-d'),
                'contenus' => $contenus,
            ];
        }

        // Séances passées
        $precedentes = [];
        foreach ($fiche->getConsultations() as $s) {
            if ($s->getId() !== $consultation->getId() && $s->getStatut() === 1) {
                $precedentes[] = [
                    'id'          => $s->getId(),
                    'date'        => $s->getCreatedAt()->format('Y-m-d'),
                    'medecin'     => $s->getMedecin()    ? $s->getMedecin()->getFullName()   : null,
                    'infirmier'   => $s->getInfirmier()  ? $s->getInfirmier()->getFullName() : null,
                    'salle'       => $s->getSalle()      ? $s->getSalle()->getNom()          : null,
                    'noteSeance'  => $s->getNoteSeance(),
                ];
            }
        }

        // Actes médicaux
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

        return new JsonResponse([
            'consultation' => $consultationData,
            'fiche'        => array_merge($ficheData, [
                'examens'      => $examens,
                'documents'    => $documents,
                'devis'        => $devisData,
                'consultations'=> $precedentes,
            ]),
            'actes'        => $actes,
        ]);
    }

    #[Route('/update-motif', methods: ['POST'], name: 'update_motif')]
    public function updateMotif(Request $request, EntityManagerInterface $em, int $ficheId, int $consultationId): JsonResponse
    {
        [$fiche,] = $this->getFicheAndConsultation($em, $ficheId, $consultationId);
        $data = json_decode($request->getContent(), true) ?? [];
        $fiche
            ->setMotif($data['motif'] ?? $fiche->getMotif())
            ->setHistoireMaladie($data['histoireMaladie'] ?? $fiche->getHistoireMaladie())
            ->setSoinsAnterieurs($data['soinsAnterieurs'] ?? $fiche->getSoinsAnterieurs());
        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    #[Route('/update-examens', methods: ['POST'], name: 'update_examens')]
    public function updateExamens(Request $request, EntityManagerInterface $em, int $ficheId, int $consultationId): JsonResponse
    {
        [$fiche,] = $this->getFicheAndConsultation($em, $ficheId, $consultationId);
        $data = json_decode($request->getContent(), true) ?? [];

        // Mise à jour des examens généraux
        $fiche
            ->setExoInspection  ($data['exoInspection']     ?? '')
            ->setExoPalpation   ($data['exoPalpation']      ?? '')
            ->setEndoInspection ($data['endoInspection']    ?? '')
            ->setEndoPalpation  ($data['endoPalpation']     ?? '')
            ->setOcclusion      ($data['occlusion']         ?? '')
            ->setExamenParodontal($data['examenParodontal'] ?? '')
            ->setDiagnostic     ($data['diagnostic']        ?? '');


        $toothsCheck = $fiche->getToothsCheck();
        if (isset($data['examensDentaires']) && is_array($data['examensDentaires'])) {
             foreach ($data['examensDentaires'] as $tooth => $result) {
                $toothsCheck[$tooth] = $result; 
             }
        }
        $fiche->setToothsCheck($toothsCheck);
        $em->persist($fiche);

        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    #[Route('/update-traitements', methods: ['POST'], name: 'update_traitements')]
    public function updateTraitements(Request $request, EntityManagerInterface $em, int $ficheId, int $consultationId): JsonResponse
    {
        [$fiche, $c] = $this->getFicheAndConsultation($em, $ficheId, $consultationId);
        $data  = json_decode($request->get('data'), true) ?? [];
        $files = $request->files->get('documentsFiles', []);

        // Mise à jour des textes
        $fiche
            ->setTraitementUrgence       ($data['traitementUrgence']      ?? '')
            ->setTraitementDentaire      ($data['traitementDentaire']     ?? '')
            ->setTraitementParodontal    ($data['traitementParodontal']   ?? '')
            ->setTraitementOrthodontique ($data['traitementOrthodontique']?? '')
            ->setAutres                  ($data['autres']                 ?? '');

        // Supprimer anciens documents
        foreach ($fiche->getDocumentsMedicaux() as $d) {
            $em->remove($d);
        }
        

        // (Re)créer les documents
        $fs        = new Filesystem();
        $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/documents';
        if (!$fs->exists($uploadDir)) {
            $fs->mkdir($uploadDir, 0775);
        }

        if (isset($data['documents']) && is_array($data['documents'])) {
            foreach ($data['documents'] as $i => $docData) {
                $dm = new DocumentMedical();
                $dm->setFiche($fiche)
                    ->setLibelle   ($docData['libelle']    ?? '')
                    ->setDateDossier(new \DateTime($docData['dateDossier'] ?? 'now'))
                    ->setDescription($docData['description'] ?? '');

                // Gestion du fichier
                if (isset($files[$i]) && $files[$i] instanceof UploadedFile) {
                    $file     = $files[$i];
                    $name     = uniqid('doc_').'.'.$file->guessExtension();
                    $file->move($uploadDir, $name);
                    $dm->setFichier('uploads/documents/'.$name);
                } else {
                    $dm->setFichier($docData['url'] ?? null);
                }

                $em->persist($dm);
            }
        }

        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    #[Route('/update-devis', methods: ['POST'], name: 'update_devis')]
    public function updateDevis(Request $request, EntityManagerInterface $em, int $ficheId, int $consultationId, DevisRepository $repo): JsonResponse
    {
        [$fiche, $consultation] = $this->getFicheAndConsultation($em, $ficheId, $consultationId);
        $data = json_decode($request->getContent(), true) ?? [];

        $oldDevis = $repo->findOneBy([
            'fiche' => $fiche,  // filtre sur la relation ManyToOne
            'type'  => 0           // et sur le champ `type`
        ]);
        // Créer un nouveau devis

        $devis = $oldDevis ?? New Devis(); 
        $devis->setFiche($fiche)
                ->setDate(new \DateTime($data['date'] ?? 'now'))
                ->SetType(0)
                ->setStatut(0)
                ->SetMontant(0);
        $em->persist($devis);

        foreach ($devis->getContenus() as $contenu) {
            // 1. on détache le contenu de la collection Devis
            $devis->removeContenu($contenu);
            // 2. on marque explicitement l'entité pour suppression
            $em->remove($contenu);
        }
        
        $amount = 0;
        // Ajouter les contenus
         if (isset($data['contenus']) && is_array($data['contenus'])) {
             foreach ($data['contenus'] as $c) {
                 $cd = new ContenuDevis();
                 $cd->setDevis($devis)
                    ->setDesignation($c['designation'] ?? '')
                    ->setQte   ($c['qte']         ?? 1)
                    ->setMontant    ($c['montant']     ?? 0);
                $amount += $cd->getMontant() * $cd->getQte();
                $cd->setMontantTotal($amount);
                 $em->persist($cd);
               
             }
         }
        $devis->setMontant($amount);
        $em->persist($devis);

        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    #[Route('/update', methods: ['POST'], name: 'update')]
    public function updateConsultation(Request $request, EntityManagerInterface $em, int $ficheId, int $consultationId): JsonResponse
    {
        [, $consultation] = $this->getFicheAndConsultation($em, $ficheId, $consultationId);
        $data = json_decode($request->getContent(), true) ?? [];

        // Relations
        if (!empty($data['medecinId'])) {
            $consultation->setMedecin($em->getReference(Employe::class, $data['medecinId']));
        }
        if (!empty($data['infirmierId'])) {
            $consultation->setInfirmier($em->getReference(Employe::class, $data['infirmierId']));
        }
        if (!empty($data['salleId'])) {
            $consultation->setSalle($em->getReference(Salle::class, $data['salleId']));
        }
        $consultation->setNoteSeance($data['noteSeance'] ?? '');

        // Actes médicaux
        foreach ($consultation->getActes() as $a) {
            $em->remove($a);
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
                $em->persist($act);
            }
        }

        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    #[Route('/cloture', methods: ['POST'], name: 'cloture')]
    public function clotureConsultation(EntityManagerInterface $em, int $ficheId, int $consultationId): JsonResponse
    {
        [$fiche, $consultation] = $this->getFicheAndConsultation($em, $ficheId, $consultationId);
        // Ne change que le statut de la consultation courante
        
        $facture = New Devis(); 
        $facture ->setFiche($fiche)
                ->setDate(new \DateTime( 'now'))
                ->SetType(1)
                ->setStatut(0)
                ->SetMontant(0);
        $em->persist($facture );

        foreach ($facture->getContenus() as $contenu) {
            // 1. on détache le contenu de la collection Devis
            $facture->removeContenu($contenu);
            // 2. on marque explicitement l'entité pour suppression
            $em->remove($contenu);
        }
        
        $amount = 0;
        // Ajouter les contenus
             foreach ($consultation->getActes() as $a) {
                 $cd = new ContenuDevis();
                 $cd->setDevis($facture)
                    ->setDesignation($a->getDescription() ?? '')
                    ->setQte   ($a->getQuantite()         ?? 1)
                    ->setMontant    ($a->getPrix()     ?? 0);
                $amount += $cd->getMontant() * $cd->getQte();
                $cd->setMontantTotal($amount);
                $em->persist($cd);
               
             }
         
        $facture->setMontant($amount);
        $facture->setReste($amount);
        $em->persist($facture);

        $em->flush();

        $consultation->setStatut(1);
        $em->flush();
        return new JsonResponse(['success' => true]);
    }
}
