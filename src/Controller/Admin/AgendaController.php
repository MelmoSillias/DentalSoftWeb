<?php

namespace App\Controller\Admin;

use App\Controller\MedecinController;
use App\Entity\Conge;
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

final class AgendaController extends AbstractController
{
#[Route('/admin/agenda', name: 'app_admin_agenda')]
    public function agenda(): Response
    {
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController', 'active_page' => 'agenda'
        ]);
    }

    // ==== Agenda: Rendez-vous ====
    #[Route('/admin/agenda/rendez-vous', name: 'app_admin_rendez_vous')]
    public function rendezVous(EmployeRepository $medecinRepo, RdvRepository $rdvRepository): Response
    {
        $medecins = $medecinRepo->findBy(['type' => 'medecin']); // Suppose que le type "medecin" est utilisé
        $rdvs = $rdvRepository->findAll(); // Récupération des rendez-vous

        return $this->render('admin/agenda/rendezvous.html.twig', [
            'controller_name' => 'AdminController',
            'active_page' => 'rendez_vous',
            'medecins' => $medecins,
            'rdvs' => $rdvs // Ajout des rendez-vous
        ]);
    }

    // ==== Agenda: Événements ====
    #[Route('/admin/agenda/evenements', name: 'app_admin_evenements')]
    public function calendar(): Response
    {
        return $this->render('admin/agenda/evenements.html.twig', [
            'controller_name' => 'AdminController',
            'active_page' => 'evenements'
        ]);
    }

    #[Route('/api/event/delete/{id}', name: 'api_event_delete', methods: ['DELETE'])]
    public function deleteBooking(int $id, EntityManagerInterface $em, BookingRepository $bookingRepository): JsonResponse
    {
        $booking = $bookingRepository->find($id);

        if (!$booking) {
            return new JsonResponse(['success' => false, 'message' => 'Événement non trouvé'], 404);
        }

        $em->remove($booking);
        $em->flush();

        return new JsonResponse(['success' => true]);
}


    // ==== Agenda: Jours Congés ====
    #[Route('/admin/agenda/jours-conges', name: 'app_admin_jours_conges')]
    public function joursConges(): Response
    {
        return $this->render('admin/agenda/joursconges.html.twig', [
            'controller_name' => 'AdminController',
            'active_page' => 'jours_conges'
        ]);
    }

    #[Route('/api/event/validate/{id}', name: 'api_event_validate', methods: ['POST'])]
    public function validateEvent(int $id, EntityManagerInterface $em, BookingRepository $bookingRepository): JsonResponse
    {
        $booking = $bookingRepository->find($id);

        if (!$booking) {
            return new JsonResponse(['success' => false, 'message' => 'Événement introuvable'], 404);
        }

        $booking->setStatut(1);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

}
