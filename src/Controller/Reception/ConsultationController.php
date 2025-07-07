<?php

namespace App\Controller\Reception;

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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ConsultationController extends AbstractController
{
    // src/Controller/Admin/ConsultationController.php

#[Route('/reception/consultation/en-attente', name: 'app_reception_consultations_pending')]
public function pendingConsultations(ConsultationRepository $consultRepo): Response
{
    // 1. Récupère les consultations en attente (state = 0)
    $consultations = $consultRepo->findPendingConsultations();

    // 2. Construit le même array que votre endpoint .json
    $consultationsData = array_map(function(Consultation $c) {
        // détermine s’il y a au moins une fiche d’observation
        $lastFiche = $c->getPatient()
                       ->getFichesObservation()
                       ->filter(fn($f) => $f !== null)
                       ->last();

        return [
            'id'        => $c->getId(),
            'patient'   => $c->getPatient()->getNom() . ' ' . $c->getPatient()->getPrenom(),
            'medecin'   => $c->getMedecin()   ? $c->getMedecin()->getNom() : null,
            'dateDebut' => $c->getCreatedAt()->format('Y-m-d H:i'),
            'hasFiche'  => $lastFiche ? true : false,
            'fiche'     => $c->getFiche()      // objet FicheObservation, que Twig pourra manipuler
        ];
    }, $consultations);

    // 3. Passe tout ça au template
    return $this->render('reception/pendingconsultations.html.twig', [
        'consultations'     => $consultations,     // vos entités pour boucler en Twig
        'consultationsData' => $consultationsData, // le tableau “prêt à l’emploi” pour un <script> ou un data-attribute
        'active_page'       => 'consultations_pending',
    ]);
}


    // src/Controller/Admin/ConsultationController.php
    #[Route('/reception/consultation/en-attente.json', name: 'app_reception_consultation_en_attente_json', methods:['GET'])]
    public function enAttenteJson(ConsultationRepository $repo): JsonResponse
    {

        $consults = $repo->findBy(['state' => 0]);

        
        $data = array_map(fn($c) => [
            'id'        => $c->getId(),
            'patient'   => $c->getPatient()->getNom().' '.$c->getPatient()->getPrenom(),
            'medecin'   => $c->getMedecin()->getNom(),
            'dateDebut' => $c->getCreatedAt()->format('Y-m-d H:i'),
            'hasfiche' => $c->getPatient()->getFichesObservation()->filter(fn($f) => $f !== null)->last() ? true : false,
            'fiche' => $c->getFiche()
        ], $consults);

    return $this->json($data);
    }
   

    
}