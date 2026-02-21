<?php

namespace App\Controller\Admin;

use App\Service\CashdeskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class CaisseController extends AbstractController
{
    public function __construct(private CashdeskService $cashdeskService)
    {
    }

    #[Route('/admin/caisse', name: 'app_admin_caisse')]
    public function caisse(): Response
    {
        return $this->render('admin/caisse.html.twig', [
            'active_page' => 'caisse'
        ]);
    }
}
