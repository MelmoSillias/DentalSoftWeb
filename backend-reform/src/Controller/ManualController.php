<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ManualController extends AbstractController{
    #[Route('/manual', name: 'app_manual')]
    public function index(): Response
    {
        return $this->render('manual/index.html.twig', [
            'controller_name' => 'ManualController',
            'active_page' => 'manual'
        ]);
    }
}
