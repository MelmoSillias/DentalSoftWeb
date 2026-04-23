<?php

namespace App\Controller\Admin;

use App\Service\AgendaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class AgendaController extends AbstractController
{
    public function __construct(private AgendaService $agendaService)
    {
    }

    #[Route('/admin/agenda', name: 'app_admin_agenda')]
    public function agenda(): Response
    {
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController', 'active_page' => 'agenda'
        ]);
    }

    #[Route('/admin/agenda/rendez-vous', name: 'app_admin_rendez_vous')]
    public function rendezVous(): Response
    {
        $context = $this->agendaService->getRendezVousContext(null, false);

        return $this->render('admin/agenda/rendezvous.html.twig', [
            'controller_name' => 'AdminController',
            'active_page' => 'rendez_vous',
            'medecins' => $context['medecins'],
            'rdvs' => $context['rdvs'],
        ]);
    }

    #[Route('/admin/agenda/evenements', name: 'app_admin_evenements')]
    public function calendar(): Response
    {
        return $this->render('admin/agenda/evenements.html.twig', [
            'controller_name' => 'AdminController',
            'active_page' => 'evenements'
        ]);
    }

    #[Route('/admin/agenda/jours-conges', name: 'app_admin_jours_conges')]
    public function joursConges(): Response
    {
        return $this->render('admin/agenda/joursconges.html.twig', [
            'controller_name' => 'AdminController',
            'active_page' => 'jours_conges'
        ]);
    }

}
