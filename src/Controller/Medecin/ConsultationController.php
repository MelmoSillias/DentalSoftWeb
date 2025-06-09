<?php

namespace App\Controller\Medecin;

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

final class ConsultationController extends AbstractController
{
    // src/Controller/Admin/ConsultationController.php

#[Route('/admin/consultation/en-attente', name: 'consultations_pending')]
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
    return $this->render('admin/pendingconsultations.html.twig', [
        'consultations'     => $consultations,     // vos entités pour boucler en Twig
        'consultationsData' => $consultationsData, // le tableau “prêt à l’emploi” pour un <script> ou un data-attribute
        'active_page'       => 'consultations_pending',
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
            'hasfiche' => $c->getPatient()->getFichesObservation()->filter(fn($f) => $f !== null)->last() ? true : false,
            'fiche' => $c->getFiche()
        ], $consults);

    return $this->json($data);
    }
   
    #[Route('/admin/consultation/{id}/editer', name: 'consultation_edit', methods: ['GET'])]
    public function editConsultation(
        int $id,
        ConsultationRepository $consultationRepo,
        EmployeRepository $employeRepo,
        SalleRepository $salleRepo,
        EntityManagerInterface $em
    ): Response {
        $consultation = $consultationRepo->find($id);

        if (!$consultation) {
            throw $this->createNotFoundException('Consultation non trouvée.');
        }

        // Dernière fiche du patient (même si pas liée à la consultation)
        $fiche = $consultation->getPatient()->getFichesObservation()
            ->filter(fn($f) => $f !== null)
            ->last();

        if (!$consultation->getFiche()) $consultation->SetFiche($fiche);

        $em->persist($consultation); 
        $em->flush();

        $medecins = $employeRepo->findBy(['type' => 'medecin']);
        $infirmiers = $employeRepo->findBy(['type' => 'infirmier']);
        $salles = $salleRepo->findAll();

        return $this->render('admin/editConsultation.html.twig', [
            'id' => $id,
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

    #[Route('/admin/consultation/{id}/editer/new', name: 'consultation_edit_new', methods: ['GET'])]
    public function editConsultationNewFiche(
        int $id,
        ConsultationRepository $consultationRepo,
        EmployeRepository $employeRepo,
        SalleRepository $salleRepo,
        EntityManagerInterface $em
    ): Response {
        $consultation = $consultationRepo->find($id);

        if (!$consultation) {
            throw $this->createNotFoundException('Consultation non trouvée.');
        }

        // Nouvelle fiche du patient (même si pas liée à la consultation)
        $fiche = New FicheObservation();
        $fiche->setPatient($consultation->getPatient());

        $em->persist($fiche); 

        $medecins = $employeRepo->findBy(['type' => 'medecin']);
        $infirmiers = $employeRepo->findBy(['type' => 'infirmier']);
        $salles = $salleRepo->findAll();

        $consultation->setFiche($fiche);
        
        $em->persist($consultation); 
        $em->flush();

        return $this->render('admin/editConsultation.html.twig', [
            'id' => $id,
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

    // src/Controller/Admin/ConsultationController.php

#[Route('/admin/consultation/{id}/details.json', name: 'consultation_details_json', methods: ['GET'])]
public function consultationDetailsJson(ConsultationRepository $repo, int $id): JsonResponse
{
    $c = $repo->find($id);
    if (!$c) {
        throw new NotFoundHttpException("Consultation $id introuvable");
    }

    // Prépare les actes
    $actesData = [];
    foreach ($c->getActes() as $a) {
        $actesData[] = [
            'dent'        => $a->getDent(),
            'type'        => $a->getType(),
            'description' => $a->getDescription(),
            'prix'        => $a->getPrix(),
            'quantite'    => $a->getQuantite(),
        ];
    }

    // Réponse enrichie
    $data = [
        'id'          => $c->getId(),
        'date'        => $c->getCreatedAt()->format('Y-m-d H:i'),
        'patient'     => $c->getPatient()->getNom() . ' ' . $c->getPatient()->getPrenom(),
        'medecin'     => $c->getMedecin()?->getNom(),
        'infirmier'   => $c->getInfirmier()?->getNom(),
        'salle'       => $c->getSalle()?->getNom(),
        'noteSeance'  => $c->getNoteSeance(),
        'actes'       => $actesData,
    ];

    return new JsonResponse($data);
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
}