<?php

namespace App\Controller\Reception;

use App\Controller\MedecinController;
use App\Entity\Conge;
use App\Entity\Employe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Salle;
use App\Entity\Rdv;
use App\Repository\SalleRepository;
use App\Repository\RdvRepository;
use App\Form\SomeFormType; // Replace 'SomeFormType' with the actual form class name
use App\Repository\BookingRepository;
use App\Repository\CongeRepository;
use App\Repository\EmployeRepository;

class AgendaController extends AbstractController
{
    #[Route('/reception/agenda', name: 'app_reception_agenda')]  
    public function rendezVous(EmployeRepository $medecinRepo, RdvRepository $rdvRepository): Response
    { 
        
        $medecins = $medecinRepo->findBy(["type" => "Médecin"]);
        $rdvs = $rdvRepository->findAll(); // Récupération des rendez-vous

        return $this->render('reception/rendezvous.html.twig', [
            'controller_name' => 'AdminController',
            'active_page' => 'agenda',
            'medecins' => $medecins,
            'rdvs' => $rdvs // Ajout des rendez-vous
        ]);
    }

}
