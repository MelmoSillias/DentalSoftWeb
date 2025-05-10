<?php

namespace App\Controller;

use App\Entity\ActePose;
use App\Entity\Caisse;
use App\Entity\Consommable;
use App\Entity\Employe;
use App\Entity\Facture;
use App\Entity\Salle;
use App\Entity\Stock;
use App\Entity\Transaction;
use App\Form\SalleType;
use App\Repository\ActePoseRepository;
use App\Repository\ConsommableRepository;
use App\Repository\ConsultationRepository;
use App\Repository\EmployeRepository;
use App\Repository\FactureRepository;
use App\Repository\PatientRepository;
use App\Repository\SalleRepository;
use App\Repository\RdvRepository;
use App\Repository\SalaireRepository;
use App\Repository\StockRepository;
use App\Repository\TraitementRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Collection;

final class AdminController extends AbstractController
{
    // ==== Dashboard ====
    #[Route('/admin/dashbord', name: 'app_admin')]
    public function index(
    ): Response {
        // SECTION 1 : Statistiques principales

        return $this->render('appbase.html.twig', [

            'controller_name' => 'AdminController', 'active_page' => 'dashboard'
                  ]);
                }   
}