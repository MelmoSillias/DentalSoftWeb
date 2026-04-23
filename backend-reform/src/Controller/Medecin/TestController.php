<?php

namespace App\Controller\Medecin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TestController extends AbstractController{
    #[Route('/medecin/test', name: 'app_medecin_test')]
    public function index(): Response
    {
        return $this->render('medecin/manual/index.html.twig', [
            'controller_name' => 'TestController',
            'active_page' => 'test'
        ]);
    }
}