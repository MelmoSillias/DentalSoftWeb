<?php

namespace App\Controller\Medecin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ManualController extends AbstractController{
    #[Route('/medecin/manual', name: 'app_medecin_manual')]
    public function index(): Response
    {
        return $this->render('medecin/manual/index.html.twig', [
            'controller_name' => 'ManualController',
            'active_page' => 'manual'
        ]);
    }
}