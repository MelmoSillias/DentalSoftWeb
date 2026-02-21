<?php

namespace App\Controller\Admin;

use App\Service\ConsommableService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ConsommableController extends AbstractController
{
    public function __construct(private ConsommableService $consommableService)
    {
    }

    #[Route('admin/consommables', name: 'app_admin_consumables')]
    public function Consumables(): Response
    {
        $data = $this->consommableService->listConsumablesWithVariations();

        return $this->render('admin/consumables.html.twig', [
            'active_page' => 'consumables',
            'consommables' => $data['consommables'],
            'variations' => $data['variations'],
        ]);
    }
}

