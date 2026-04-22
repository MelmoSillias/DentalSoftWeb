<?php

namespace App\Controller\Medecin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/medecin/dashboard', name: 'app_medecin_dashboard')]
    public function index(): Response
    {
        return $this->render('medecin/index.html.twig', [
            'controller_name' => 'DashboardController',
            'active_page' => 'dashboard',
        ]);
    }
}