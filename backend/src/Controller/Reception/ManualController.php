<?php

namespace App\Controller\Reception;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ManualController extends AbstractController{
    #[Route('/reception/manual', name: 'app_reception_manual')]
    public function index(): Response
    {
        return $this->render('reception/manual/index.html.twig', [
            'controller_name' => 'ManualController',
            'active_page' => 'manual'
        ]);
    }
}