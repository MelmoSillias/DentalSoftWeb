<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReceptionnisteController extends AbstractController
{
    #[Route('/receptionniste', name: 'app_receptionniste')]
    public function index(): Response
    {
        return $this->render('receptionniste/index.html.twig', [
            'controller_name' => 'ReceptionnisteController',
        ]);
    }
}
