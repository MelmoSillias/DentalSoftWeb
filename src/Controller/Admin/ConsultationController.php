<?php

namespace App\Controller\Admin;

use App\Entity\ActeMedical;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Consultation;
use App\Entity\ContenuDevis;
use App\Entity\Devis;
use App\Entity\DocumentMedical;
use App\Entity\ExamenDentaire;
use App\Entity\FicheObservation;
use App\Repository\ConsultationRepository; 
use App\Repository\SalleRepository;
use App\Repository\EmployeRepository; 
use DateTime;

final class ConsultationController extends AbstractController
{
    #[Route('/admin/consultation/en-attente', name: 'consultations_pending')]
    public function pendingConsultations(ConsultationRepository $consultationRepo): Response
    {
        return $this->render('admin/pendingconsultations.html.twig', [
            'consultations' => $consultationRepo->findPendingConsultations(),

            'active_page' => 'consultations_pending'
        ]);
    }

    // src/Controller/Admin/ConsultationController.php
    #[Route('/admin/consultation/en-attente.json', name: 'consultation_en_attente_json', methods:['GET'])]
    public function enAttenteJson(ConsultationRepository $repo): JsonResponse
    {
        $consults = $repo->findBy(['state' => 0]);
        $data = array_map(fn($c) => [
            'id'        => $c->getId(),
            'patient'   => $c->getPatient()->getNom().' '.$c->getPatient()->getPrenom(),
            'medecin'   => $c->getMedecin()->getNom(),
            'dateDebut' => $c->getCreatedAt()->format('Y-m-d H:i'),
        ], $consults);

    return $this->json($data);
    }
   
    #[Route('/admin/consultation/{id}/editer', name: 'consultation_edit', methods: ['GET'])]
    public function editConsultation(
        int $id,
        ConsultationRepository $consultationRepo,
        EmployeRepository $employeRepo,
        SalleRepository $salleRepo,
    ): Response {
        $consultation = $consultationRepo->find($id);

        if (!$consultation) {
            throw $this->createNotFoundException('Consultation non trouvée.');
        }

        // Dernière fiche du patient (même si pas liée à la consultation)
        $fiche = $consultation->getPatient()->getFichesObservation()
            ->filter(fn($f) => $f !== null)
            ->last();

        $medecins = $employeRepo->findBy(['type' => 'medecin']);
        $infirmiers = $employeRepo->findBy(['type' => 'infirmier']);
        $salles = $salleRepo->findAll();

        return $this->render('admin/editConsultation.html.twig', [
            'consultation'  => $consultation,
            'patient'       => $consultation->getPatient(),
            'fiche'         => $fiche,
            'consultationsFiche' => $fiche ? $fiche->getConsultations()->filter(fn($c) => $c->getStatut() === 1) : [], 
            'medecins'      => $medecins,
            'infirmiers'    => $infirmiers,
            'salles'        => $salles,
            'active_page'   => 'consultations_pending',
        ]);
    }

    #[Route('/admin/consultation/{id}/details', name: 'consultation_details')]
    public function consultationDetails(int $id, ConsultationRepository $consultationRepo): Response
    {
        $consultation = $consultationRepo->findFullConsultation($id);

        if (!$consultation) {
            throw $this->createNotFoundException('Consultation introuvable');
        }

        return $this->render('admin/consultationDetails.html.twig', [
            'consultation' => $consultation,
            'actes' => $consultation->getActes(),
            'active_page' => 'consultations_closed'
        ]);
    }

    #[Route('/api/consultation/{id}', name: 'api_consultation_delete', methods: ['DELETE'])]
    public function deleteConsultation(int $id, ConsultationRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $consultation = $repo->find($id);

        if (!$consultation) {
            return $this->json(['message' => 'Consultation introuvable'], 404);
        }

        $em->remove($consultation);
        $em->flush();

        return $this->json(['message' => 'Consultation supprimée avec succès']);
    }


    #[Route('/admin/consultations/closed', name: 'consultations_closed')]
    public function closedConsultations(ConsultationRepository $consultationRepo): Response
    {
        return $this->render('admin/closedconsultations.html.twig', [
            'controller_name' => 'AdminController', 'active_page' => 'consultations_closed'
        ]);
    }

    #[Route('/api/fiche/{id}/update', name: 'api_fiche_update', methods: ['POST'])]
    public function updateFicheConsultation(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ConsultationRepository $consultationRepo,
        EmployeRepository $employeRepo,
        SalleRepository $salleRepo
    ): JsonResponse {
        $consultation = $consultationRepo->find($id);
        if (!$consultation) {
            return new JsonResponse(['error' => 'Consultation non trouvée.'], 404);
        }

    $dataJson = $request->request->all();  
    $data = json_decode($dataJson['data'], true);

    $files = $request->files->all();  

    if (!$data) {
        return new JsonResponse(['error' => 'Données JSON invalides.'], 400);
    }


    $consultation->setNoteSeance($data['consultation']['noteSeance'] ?? '');

    $medecin = $data['consultation']['medecinId'] ?? null;
    $infirmier = $data['consultation']['infirmierId'] ?? null;
    $salle = $data['consultation']['salleId'] ?? null;

    $consultation->setMedecin($medecin ? $employeRepo->find($medecin) : null);
    $consultation->setInfirmier($infirmier ? $employeRepo->find($infirmier) : null);
    $consultation->setSalle($salle ? $salleRepo->find($salle) : null);

    // === 2. Fiche d’observation associée === 
    if ($data['isNewFiche']) { 
        $fiche = new FicheObservation();
        $fiche->setPatient($consultation->getPatient());
        $fiche->addConsultation($consultation);
        $em->persist($fiche);
        $consultation->setFiche($fiche);
    }else{
        $fiche = $consultation->getPatient()->getFichesObservation()
            ->filter(fn($f) => $f !== null)
            ->last();
            if (!$fiche) {
                $fiche = new FicheObservation();
                $fiche->setPatient($consultation->getPatient());  
                $fiche->addConsultation($consultation);

                $em->persist($fiche);
                
            }else { 
                $fiche->addConsultation($consultation);
            }
    }

    $fiche
        ->setMotif($data['motif'] ?? '')
        ->setHistoireMaladie($data['histoireMaladie'] ?? '')
        ->setSoinsAnterieurs($data['soinsAnterieurs'] ?? '')
        ->setExoInspection($data['exoInspection'] ?? '')
        ->setExoPalpation($data['exoPalpation'] ?? '')
        ->setEndoInspection($data['endoInspection'] ?? '')
        ->setEndoPalpation($data['endoPalpation'] ?? '')
        ->setOcclusion($data['occlusion'] ?? '')
        ->setExamenParodontal($data['examenParodontal'] ?? '')
        ->setDiagnostic($data['diagnostic'] ?? '')
        ->setTraitementUrgence($data['traitementUrgence'] ?? '')
        ->setTraitementDentaire($data['traitementDentaire'] ?? '')
        ->setTraitementParodontal($data['traitementParodontal'] ?? '')
        ->setTraitementOrthodontique($data['traitementOrthodontique'] ?? '')
        ->setAutres($data['autres'] ?? '');

    // === 3. Suppression + réinsertion des sous-éléments ===
    foreach ($consultation->getActes() as $a) $em->remove($a);
    foreach ($fiche->getExamensDentaires() as $e) $em->remove($e);
    foreach ($fiche->getDocumentsMedicaux() as $d) $em->remove($d);

    // === Actes médicaux ===
    foreach ($data['actes'] as $a) {
        $acte = new ActeMedical();
        $acte->setConsultation($consultation);
        $acte->setDent($a['dent'] ?? '');
        $acte->setType($a['type'] ?? '');
        $acte->setDescription($a['description'] ?? '');
        $acte->setPrix($a['prix'] ?? 0);
        $acte->setQuantite($a['quantite'] ?? 1);
        $em->persist($acte);
    }

    // === Examens dentaires ===
    foreach ($data['examens'] as $ex) {
        $exam = new ExamenDentaire();
        $exam->setFiche($fiche);
        $exam->setDate(new \DateTime($ex['date'] ?? 'now'));
        $exam->setDesignation($ex['designation'] ?? '');
        $exam->setResultat($ex['resultat'] ?? '');
        $em->persist($exam);
    }

    foreach ($fiche->getDocumentsMedicaux() as $d) $em->remove($d);
    // === Documents médicaux (avec gestion de fichier)
$documentsData = $data['documents'] ?? [];
$documentsFiles = $request->files->all('documentsFiles') ?? [];

foreach ($documentsData as $i => $doc) {
    $document = new DocumentMedical();
    $document->setFiche($fiche);
    $document->setLibelle($doc['libelle'] ?? '');
    $document->setDateDossier(new \DateTime($doc['dateDossier'] ?? 'now'));
    $document->setDescription($doc['description'] ?? '');

    $fichierUrl = $doc['fichier'] ?? null;

    // Traitement du fichier s'il existe
    if (isset($documentsFiles[$i]) && $documentsFiles[$i] instanceof UploadedFile) {
        $file = $documentsFiles[$i];

        // Générer un nom unique
        $filename = uniqid('doc_') . '.' . $file->guessExtension();

        // Définir le chemin de destination
        $destination = $this->getParameter('kernel.project_dir') . '/public/uploads/documents';

        // Créer le dossier s’il n’existe pas
        if (!file_exists($destination)) {
            mkdir($destination, 0775, true);
        }

        // Déplacer le fichier
        $file->move($destination, $filename);

        // Enregistrer le chemin relatif
        $document->setFichier('uploads/documents/' . $filename);
} elseif ($fichierUrl) {
    // Aucun fichier uploadé → on garde l’ancien chemin
    $document->setFichier($fichierUrl);
}

    $em->persist($document);
}

    // === Devis (1 seul par fiche) ===
$devis = $fiche->getDevis() ?: new Devis();
$devis->setFiche($fiche);
$devis->setDate(new \DateTime($data['devis']['date'] ?? 'now'));

// Supprimer proprement les anciens contenus
foreach ($devis->getContenus() as $contenu) {
    $devis->removeContenu($contenu);
    $em->remove($contenu);
}
$em->flush(); // Facultatif mais utile pour forcer la suppression

// Réinsertion
$total = 0;
foreach ($data['devis']['contenus'] as $contenuData) {
    $contenu = new ContenuDevis();
    $contenu->setDevis($devis);
    $contenu->setDesignation($contenuData['designation'] ?? '');
    $contenu->setQte($contenuData['qte'] ?? 1);
    $contenu->setMontant($contenuData['montant'] ?? 0);
    $contenu->setMontantTotal($contenu->getQte() * $contenu->getMontant());

    $total += $contenu->getMontantTotal();
    $em->persist($contenu);
}

$devis->setMontant($total);
$devis->setReste($total);
$devis->setStatut(0); // 0 = devis
$em->persist($devis);


    // === Enregistrement
    $em->flush();

    return new JsonResponse(['success' => true]);
}

    #[Route('/api/consultation/{id}/cloture', name: 'api_consultation_cloture_ajax', methods: ['POST'])]
    public function clotureAjax(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ConsultationRepository $consultationRepo,
        EmployeRepository $employeRepo,
        SalleRepository $salleRepo
    ): JsonResponse {
        $consultation = $consultationRepo->find($id);
        if (!$consultation) {
            return new JsonResponse(['error' => 'Consultation non trouvée.'], 404);
        }

    $dataJson = $request->request->all();  
    $data = json_decode($dataJson['data'], true);


    if (!$data) {
        return new JsonResponse(['error' => 'Données JSON invalides.'], 400);
    }


    $consultation->setNoteSeance($data['consultation']['noteSeance'] ?? '');

    $medecin = $data['consultation']['medecinId'] ?? null;
    $infirmier = $data['consultation']['infirmierId'] ?? null;
    $salle = $data['consultation']['salleId'] ?? null;

    $consultation->setMedecin($medecin ? $employeRepo->find($medecin) : null);
    $consultation->setInfirmier($infirmier ? $employeRepo->find($infirmier) : null);
    $consultation->setSalle($salle ? $salleRepo->find($salle) : null);
    $consultation->setStatut(1); // 1 = consultation terminée

    // === 2. Fiche d’observation associée === 
    if ($data['isNewFiche']) { 
        $fiche = new FicheObservation();
        $fiche->setPatient($consultation->getPatient());
        $fiche->addConsultation($consultation);
        $em->persist($fiche);
        $consultation->setFiche($fiche);
    }else{
        $fiche = $consultation->getPatient()->getFichesObservation()
            ->filter(fn($f) => $f !== null)
            ->last();
            if (!$fiche) {
                $fiche = new FicheObservation();
                $fiche->setPatient($consultation->getPatient());  
                $fiche->addConsultation($consultation);

                $em->persist($fiche);
                
            }else { 
                $fiche->addConsultation($consultation);
            }
    }

    $fiche
        ->setMotif($data['motif'] ?? '')
        ->setHistoireMaladie($data['histoireMaladie'] ?? '')
        ->setSoinsAnterieurs($data['soinsAnterieurs'] ?? '')
        ->setExoInspection($data['exoInspection'] ?? '')
        ->setExoPalpation($data['exoPalpation'] ?? '')
        ->setEndoInspection($data['endoInspection'] ?? '')
        ->setEndoPalpation($data['endoPalpation'] ?? '')
        ->setOcclusion($data['occlusion'] ?? '')
        ->setExamenParodontal($data['examenParodontal'] ?? '')
        ->setDiagnostic($data['diagnostic'] ?? '')
        ->setTraitementUrgence($data['traitementUrgence'] ?? '')
        ->setTraitementDentaire($data['traitementDentaire'] ?? '')
        ->setTraitementParodontal($data['traitementParodontal'] ?? '')
        ->setTraitementOrthodontique($data['traitementOrthodontique'] ?? '')
        ->setAutres($data['autres'] ?? '');

    // === 3. Suppression + réinsertion des sous-éléments ===
    foreach ($consultation->getActes() as $a) $em->remove($a);
    foreach ($fiche->getExamensDentaires() as $e) $em->remove($e);
    foreach ($fiche->getDocumentsMedicaux() as $d) $em->remove($d);

    // === Actes médicaux ===
    foreach ($data['actes'] as $a) {
        $acte = new ActeMedical();
        $acte->setConsultation($consultation);
        $acte->setDent($a['dent'] ?? '');
        $acte->setType($a['type'] ?? '');
        $acte->setDescription($a['description'] ?? '');
        $acte->setPrix($a['prix'] ?? 0);
        $acte->setQuantite($a['quantite'] ?? 1);
        $em->persist($acte);
    }

    // === Examens dentaires ===
    foreach ($data['examens'] as $ex) {
        $exam = new ExamenDentaire();
        $exam->setFiche($fiche);
        $exam->setDate(new \DateTime($ex['date'] ?? 'now'));
        $exam->setDesignation($ex['designation'] ?? '');
        $exam->setResultat($ex['resultat'] ?? '');
        $em->persist($exam);
    }

    foreach ($fiche->getDocumentsMedicaux() as $d) $em->remove($d);
    // === Documents médicaux (avec gestion de fichier)
$documentsData = $data['documents'] ?? [];
$documentsFiles = $request->files->all('documentsFiles') ?? [];

foreach ($documentsData as $i => $doc) {
    $document = new DocumentMedical();
    $document->setFiche($fiche);
    $document->setLibelle($doc['libelle'] ?? '');
    $document->setDateDossier(new \DateTime($doc['dateDossier'] ?? 'now'));
    $document->setDescription($doc['description'] ?? '');

    $fichierUrl = $doc['fichier'] ?? null;

    // Traitement du fichier s'il existe
    if (isset($documentsFiles[$i]) && $documentsFiles[$i] instanceof UploadedFile) {
        $file = $documentsFiles[$i];

        // Générer un nom unique
        $filename = uniqid('doc_') . '.' . $file->guessExtension();

        // Définir le chemin de destination
        $destination = $this->getParameter('kernel.project_dir') . '/public/uploads/documents';

        // Créer le dossier s’il n’existe pas
        if (!file_exists($destination)) {
            mkdir($destination, 0775, true);
        }

        // Déplacer le fichier
        $file->move($destination, $filename);

        // Enregistrer le chemin relatif
        $document->setFichier('uploads/documents/' . $filename);
} elseif ($fichierUrl) {
    // Aucun fichier uploadé → on garde l’ancien chemin
    $document->setFichier($fichierUrl);
}

    $em->persist($document);
}

    // === Devis (1 seul par fiche) ===
$devis = $fiche->getDevis() ?: new Devis();
$devis->setFiche($fiche);
$devis->setDate(new \DateTime($data['devis']['date'] ?? 'now'));

// Supprimer proprement les anciens contenus
foreach ($devis->getContenus() as $contenu) {
    $devis->removeContenu($contenu);
    $em->remove($contenu);
}
$em->flush(); // Facultatif mais utile pour forcer la suppression

// Réinsertion
$total = 0;
foreach ($data['devis']['contenus'] as $contenuData) {
    $contenu = new ContenuDevis();
    $contenu->setDevis($devis);
    $contenu->setDesignation($contenuData['designation'] ?? '');
    $contenu->setQte($contenuData['qte'] ?? 1);
    $contenu->setMontant($contenuData['montant'] ?? 0);
    $contenu->setMontantTotal($contenu->getQte() * $contenu->getMontant());

    $total += $contenu->getMontantTotal();
    $em->persist($contenu);
}

$devis->setMontant($total);
$devis->setReste($total);
$devis->setStatut(0); // 0 = devis
$em->persist($devis);
$em->flush(); // Facultatif mais utile pour forcer la suppression

    return new JsonResponse(['success' => true]);
}
 
    #[Route('/api/consultation/{id}/json', name: 'api_consultation_json', methods: ['GET'])]
    public function consultationJson(
        int $id,
        ConsultationRepository $consultationRepo
    ): JsonResponse {
        $consultation = $consultationRepo->find($id);

        if (!$consultation) {
            return new JsonResponse(['error' => 'Consultation introuvable.'], 404);
        }

        $fiche = $consultation->getPatient()->getFichesObservation()
            ->filter(fn($f) => $f !== null)
            ->last();
        $medecin = $consultation->getMedecin();
        $infirmier = $consultation->getInfirmier();
        $salle = $consultation->getSalle();

        // Consultation en cours
        $consultationData = [
            'id' => $consultation->getId(),
            'noteSeance' => $consultation->getNoteSeance(),
            'medecin' => $medecin ? ['id' => $medecin->getId(), 'nomComplet' => $medecin->getFUllName()] : null,
            'infirmier' => $infirmier ? ['id' => $infirmier->getId(), 'nomComplet' => $infirmier->getFullName()] : null,
            'salle' => $salle ? ['id' => $salle->getId(), 'nom' => $salle->getNom()] : null,
            'ficheLiee' => $fiche ? ['id' => $fiche->getId()] : null,
        ];

        // Actes médicaux
        $actesData = [];
        foreach ($consultation->getActes() as $acte) {
            $actesData[] = [
                'id' => $acte->getId(),
                'dent' => $acte->getDent(),
                'type' => $acte->getType(),
                'description' => $acte->getDescription(),
                'prix' => $acte->getPrix(),
                'quantite' => $acte->getQuantite()
            ];
        }

        // Fiche d’observation
        $ficheData = $fiche ? [
            'id' => $fiche->getId(),
            'dateFiche' => $fiche->getCreatedAt()->format('Y-m-d H:i'),
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
            'traitementUrgence' => $fiche->getTraitementUrgence(),
            'traitementDentaire' => $fiche->getTraitementDentaire(),
            'traitementParodontal' => $fiche->getTraitementParodontal(),
            'traitementOrthodontique' => $fiche->getTraitementOrthodontique(),
            'autres' => $fiche->getAutres(),
        ] : ['id' => null];

        // Examens dentaires
        $examens = [];
        if ($fiche) {
            foreach ($fiche->getExamensDentaires() as $examen) {
                $examens[] = [
                    'id' => $examen->getId(),
                    'date' => $examen->getDate()?->format('Y-m-d'),
                    'designation' => $examen->getDesignation(),
                    'resultat' => $examen->getResultat()
                ];
            }

            // Documents médicaux
            $documents = [];
            foreach ($fiche->getDocumentsMedicaux() as $doc) {
                $documents[] = [
                    'id' => $doc->getId(),
                    'libelle' => $doc->getLibelle(),
                    'dateDossier' => $doc->getDateDossier()?->format('Y-m-d'),
                    'description' => $doc->getDescription(),
                    'url' => $doc->getFichier()
                ];
            }

            // Devis (un seul)
            $devis = $fiche->getDevis();
            $devisData = null;
            if ($devis) {
                $contenus = [];
                foreach ($devis->getContenus() as $c) {
                    $contenus[] = [
                        'id' => $c->getId(),
                        'designation' => $c->getDesignation(),
                        'qte' => $c->getQte(),
                        'montant' => $c->getMontant()
                    ];
                }

                $devisData = [
                    'id' => $devis->getId(),
                    'date' => $devis->getDate()?->format('Y-m-d'),
                    'montant' => $devis->getMontant(),
                    'reste' => $devis->getReste(),
                    'contenus' => $contenus
                ];
            }

            // Consultations antérieures
            $precedentes = [];
            foreach ($fiche->getConsultations() as $c) {

                if ($c->getId() !== $consultation->getId() && $c->getStatut() === 1) {
                    $precedentes[] = [
                        'id' => $c->getId(),
                        'date' => $c->getCreatedAt()?->format('Y-m-d'),
                        'noteSeance' => $c->getNoteSeance(),
                        'statut' => $c->getStatut(),
                        'medecin' => $c->getMedecin()?->getFullName(),
                        'infirmier' => $c->getInfirmier()?->getFullName(),
                        'salle' => $c->getSalle()?->getNom()
                    ];
                }
            }
        } else {
            $documents = [];
            $devisData = null;
            $precedentes = [];
        }

        return new JsonResponse([
            'consultation' => $consultationData,
            'fiche' => array_merge($ficheData, [
                'examens' => $examens,
                'documents' => $documents,
                'devis' => $devisData,
                'consultations' => $precedentes
            ]),
            'actes' => $actesData
        ]);
    }

}
