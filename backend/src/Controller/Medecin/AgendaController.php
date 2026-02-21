<?php

namespace App\Controller\Medecin;

use App\Service\AgendaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class AgendaController extends AbstractController
{
    public function __construct(private AgendaService $agendaService)
    {
    }

    #[Route('/medecin/agenda', name: 'app_medecin_agenda')]
    public function rendezVous(): Response
    {
        $context = $this->agendaService->getRendezVousContext($this->getUser(), true);

        return $this->render('medecin/agenda/rendezvous.html.twig', [
            'controller_name' => 'AdminController',
            'active_page' => 'agenda',
            'medecins' => $context['medecins'],
            'rdvs' => $context['rdvs'],
        ]);
    }
}
