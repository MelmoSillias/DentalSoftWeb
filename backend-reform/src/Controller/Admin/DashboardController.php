<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Collection;

final class DashboardController extends AbstractController
{
    // ==== Dashboard ====
    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    public function index(): Response
    {
        // SECTION 1 : Statistiques principales

        return $this->render('admin/index.html.twig', [
            'controller_name' => 'DashboardController',
            'active_page' => 'dashboard'
        ]);
    }
}