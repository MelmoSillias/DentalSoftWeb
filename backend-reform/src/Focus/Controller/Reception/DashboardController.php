<?php

namespace App\Focus\Controller\Reception;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/reception/dashboard', name: 'app_reception_dashboard')]
    public function index(): Response
    {
        return $this->render('reception/index.html.twig', [
            'controller_name' => 'DashboardController',
            'active_page' => 'dashboard'
        ]);
    }
}