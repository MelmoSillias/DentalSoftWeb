<?php

namespace App\Controller\Reception;

use App\Entity\ContenuDevis;
use App\Entity\Devis;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Facture;
use App\Entity\PaiementDevis;
use App\Entity\Transaction;
use App\Repository\FactureRepository;
use App\Form\FactureType;
use App\Repository\DevisRepository;
use App\Repository\ModeDePaiementRepository;
use App\Repository\PaiementDevisRepository;

final class CaisseController extends AbstractController
{
#[Route('/reception/caisse', name: 'app_reception_caisse')]
    public function caisse(): Response
    { 
        return $this->render('reception/caisse.html.twig', [ 
            'active_page' => 'caisse'
        ]);
    } 
}
