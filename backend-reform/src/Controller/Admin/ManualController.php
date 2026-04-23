<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ManualController extends AbstractController{
    #[Route('/admin/manual', name: 'app_admin_manual')]
    public function index(): Response
    {
        return $this->render('admin/manual/index.html.twig', [
            'controller_name' => 'ManualController',
            'active_page' => 'manual'
        ]);
    }
}
