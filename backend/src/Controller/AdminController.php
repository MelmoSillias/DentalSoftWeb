<?php

namespace App\Controller;



use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Collection;

final class AdminController extends AbstractController
{
    // ==== Dashboard ====
    #[Route('/admin/dashbord', name: 'app_admin')]
    public function index(
    ): Response {
        // SECTION 1 : Statistiques principales

        return $this->render('admin/index.html.twig', [

            'controller_name' => 'AdminController', 'active_page' => 'dashboard'
                  ]);
                }   
}