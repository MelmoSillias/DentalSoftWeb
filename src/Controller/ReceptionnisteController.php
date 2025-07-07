<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReceptionnisteController extends AbstractController
{
    #[Route('/reception', name: 'app_reception_dashboard')]
    public function index(): Response
    {
        return $this->render('reception/index.html.twig', [
            'controller_name' => 'ReceptionnisteController','active_page' => 'dashboard']);
    }
}
