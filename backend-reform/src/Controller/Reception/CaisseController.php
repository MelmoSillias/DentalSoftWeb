<?php

namespace App\Controller\Reception;

use App\Service\CashdeskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class CaisseController extends AbstractController
{
    #[Route('/reception/caisse', name: 'app_reception_caisse')]
    public function caisse(CashdeskService $cashdeskService): Response
    {
        $context = $cashdeskService->getCaissePageContext();

        return $this->render('reception/caisse.html.twig', array_merge($context, [
            'active_page' => 'caisse',
        ]));
    }
}
