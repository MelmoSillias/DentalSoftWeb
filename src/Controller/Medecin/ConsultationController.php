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
use App\Entity\Employe;
use App\Entity\ExamenDentaire;
use App\Entity\FicheObservation;
use App\Repository\ConsultationRepository;
use App\Repository\SalleRepository;
use App\Repository\EmployeRepository;
use DateTime;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ConsultationController extends AbstractController
{
    #[Route('/medecin/consultation/en-attente', name: 'app_medecin_consultations_pending')]
    public function pendingConsultations(ConsultationRepository $consultRepo, EntityManagerInterface $em ): Response
    {   
        $consultations = $consultRepo->findPendingConsultations(); 
        $user = $this->getUser();

 
        $employe = $em->getRepository(Employe::class)->findOneBy(['user' => $user]);
        if ($employe) {
            $consultations = array_filter($consultations, function(Consultation $c) use ($employe) {
                return $c->getMedecin() && $c->getMedecin()->getId() === $employe->getId();
            });
        } 

        $consultationsData = array_map(function(Consultation $c) {
            $lastFiche = $c->getPatient()
                           ->getFichesObservation()
                           ->filter(fn($f) => $f !== null)
                           ->last();
            return [
                'id'        => $c->getId(),
                'patient'   => $c->getPatient()->getNom() . ' ' . $c->getPatient()->getPrenom(),
                'medecin'   => $c->getMedecin() ? $c->getMedecin()->getFullName() : null,
                'dateDebut' => $c->getCreatedAt()->format('Y-m-d H:i'),
                'hasFiche'  => $lastFiche ? true : false,
                'fiche'     => $c->getFiche()
            ];
        }, $consultations);

        return $this->render('medecin/pendingconsultations.html.twig', [
            'consultations'     => $consultations,
            'consultationsData' => $consultationsData,
            'active_page'       => 'consultations_pending',
        ]);
    }

    #[Route('/medecin/consultation/en-attente.json', name: 'app_medecin_consultation_en_attente_json', methods:['GET'])]
    public function enAttenteJson(ConsultationRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $consults = $repo->findBy(['state' => 0]);
        $user = $this->getUser();
        $employe = $em->getRepository(Employe::class)->findOneBy(['user' => $user]);
        if ($employe) {
            $consults = array_filter($consults, function(Consultation $c) use ($employe) {
                return $c->getMedecin() && $c->getMedecin()->getId() === $employe->getId();
            });
        } 

        $data = array_map(fn($c) => [
            'id'        => $c->getId(),
            'patient'   => $c->getPatient()->getNom().' '.$c->getPatient()->getPrenom(),
            'medecin'   => $c->getMedecin()->getFullName(),
            'dateDebut' => $c->getCreatedAt()->format('Y-m-d H:i'),
            'hasfiche' => $c->getPatient()->getFichesObservation()->filter(fn($f) => $f !== null)->last() ? true : false,
            'fiche' => $c->getFiche()
        ], $consults);

        return $this->json($data);
    }

    #[Route('/medecin/consultation/{id}/editer', name: 'app_medecin_consultation_edit', methods: ['GET'])]
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

        $fiche = $consultation->getPatient()->getFichesObservation()
            ->filter(fn($f) => $f !== null)
            ->last();
        if (!$consultation->getFiche()) $consultation->setFiche($fiche);
        $em->persist($consultation);
        $em->flush();

        $medecins = $employeRepo->findBy(['type' => 'medecin']);
        $infirmiers = $employeRepo->findBy(['type' => 'infirmier']);
        $salles = $salleRepo->findAll();

        return $this->render('medecin/editConsultation.html.twig', [
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

    #[Route('/medecin/consultation/{id}/editer/new', name: 'app_medecin_consultation_edit_new', methods: ['GET'])]
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

        $fiche = new FicheObservation();
        $fiche->setPatient($consultation->getPatient());
        $em->persist($fiche);

        $medecins = $employeRepo->findBy(['type' => 'medecin']);
        $infirmiers = $employeRepo->findBy(['type' => 'infirmier']);
        $salles = $salleRepo->findAll();

        $consultation->setFiche($fiche);
        $em->persist($consultation);
        $em->flush();

        return $this->render('medecin/editConsultation.html.twig', [
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

    #[Route('/medecin/consultation/{id}/details', name: 'app_medecin_consultation_details')]
    public function consultationDetails(int $id, ConsultationRepository $consultationRepo): Response
    {
        $consultation = $consultationRepo->findFullConsultation($id);
        if (!$consultation) {
            throw $this->createNotFoundException('Consultation introuvable');
        }

        return $this->render('medecin/consultationDetails.html.twig', [
            'consultation' => $consultation,
            'actes' => $consultation->getActes(),
            'active_page' => 'consultations_closed'
        ]);
    }

    #[Route('/medecin/consultation/{id}/details.json', name: 'app_medecin_consultation_details_json', methods: ['GET'])]
    public function consultationDetailsJson(ConsultationRepository $repo, int $id): JsonResponse
    {
        $c = $repo->find($id);
        if (!$c) {
            throw new NotFoundHttpException("Consultation $id introuvable");
        }

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

        $data = [
            'id'          => $c->getId(),
            'date'        => $c->getCreatedAt()->format('Y-m-d H:i'),
            'patient'     => $c->getPatient()->getNom() . ' ' . $c->getPatient()->getPrenom(),
            'medecin'     => $c->getMedecin()?->getFullname(),
            'infirmier'   => $c->getInfirmier()?->getNom(),
            'salle'       => $c->getSalle()?->getNom(),
            'noteSeance'  => $c->getNoteSeance(),
            'actes'       => $actesData,
        ];

        return new JsonResponse($data);
    }

    #[Route('/medecin/consultations/closed', name: 'app_medecin_consultations_closed')]
    public function closedConsultations(ConsultationRepository $consultationRepo): Response
    {
        return $this->render('medecin/closedconsultations.html.twig', [
            'controller_name' => 'AdminController', 'active_page' => 'consultations_closed'
        ]);
    }

    #[Route('/medecin/consultation/liste', name: 'app_medecin_consultations_liste')]
    public function ListeAllConsultations(ConsultationRepository $consultRepo): Response
    {
           
        return $this->render('medecin/consultations.html.twig', [ 
            'active_page'       => 'consultations_liste',
        ]);
    }
}
