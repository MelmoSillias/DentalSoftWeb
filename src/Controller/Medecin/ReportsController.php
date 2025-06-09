<?php

namespace App\Controller\Medecin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\SomeEntity;
use App\Repository\SomeRepository;
use App\Form\SomeForm;

final class ReportsController extends AbstractController
{
    #[Route('/admin/rapports', name: 'app_admin_rapports')]
    public function rapports(): Response
    {
        return $this->render('admin/rapports.html.twig', [
            'controller_name' => 'AdminController', 
            'active_page' => 'rapports'
        ]);
    }
}
