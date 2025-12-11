<?php

namespace App\Controller\Reception;

use App\Service\AgendaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AgendaController extends AbstractController
{
    public function __construct(private AgendaService $agendaService)
    {
    }

    #[Route('/reception/agenda', name: 'app_reception_agenda')]
    public function rendezVous(): Response
    {
        $context = $this->agendaService->getRendezVousContext(null, false, 'Médecin');

        return $this->render('reception/rendezvous.html.twig', [
            'controller_name' => 'AdminController',
            'active_page' => 'agenda',
            'medecins' => $context['medecins'],
            'rdvs' => $context['rdvs'],
        ]);
    }
}
