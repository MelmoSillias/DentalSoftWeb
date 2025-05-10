<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Consommable;
use App\Entity\Stock;
use App\Entity\Employe;
use App\Repository\ConsommableRepository;
use App\Repository\StockRepository;
use App\Repository\EmployeRepository;
use App\Form\ConsommableType;

final class ConsommableController extends AbstractController
{
    #[Route('admin/consommables', name: 'app_admin_consumables')]
    public function Consumables(
        ConsommableRepository $consRepo,
        StockRepository $varRepo
    ): Response {
        return $this->render('admin/consumables.html.twig', [
            'active_page' => 'consumables',
            'consommables' => $consRepo->findAll(),
            'variations' => $varRepo->findBy([], ['datePrise' => 'DESC']),
        ]);
    }

    #[Route('/consommables/add', name: 'consommable_add', methods: ['POST'])]
    public function add(Request $request, EntityManagerInterface $em): Response
    {
        $c = new Consommable();
        $c->setNom($request->request->get('nom'));
        $c->setQuantity((int)$request->request->get('quantite'));
        $c->setFournisseur($request->request->get('fournisseur'));
        $c->setLowValue((int)$request->request->get('lowValue'));

        $stock = new Stock();
        $stock->setConsommable($c);
        $stock->setQuantiteUtilisee($c->getQuantity());
        $stock->setType('Ajout');
        $stock->setDescription('Ajout d\'un nouveau consommable');
        $stock->setDatePrise(new \DateTime());
        $user = $this->getUser();
        $employee = $em->getRepository(Employe::class)->findOneBy(['user' => $user]);
        $stock->setEmployee($employee);

        $em->persist($c);
        $em->persist($stock);
        $em->flush();

        return $this->redirectToRoute('app_admin_consumables');
    }        

    #[Route('/consommables/{id}/edit', name: 'consommable_edit', methods: ['POST'])]
    public function edit(Request $request, Consommable $consommable, EntityManagerInterface $em): Response
    {
        $consommable->setNom($request->get('nom'));
        $consommable->setQuantity((int)$request->get('quantite'));
        $consommable->setFournisseur($request->get('fournisseur'));
        $em->flush();

        return $this->redirectToRoute('consommable_list');
    }

    #[Route('/consommables/{id}/retrait', name: 'consommable_retrait', methods: ['POST'])]
    public function retrait(
        Consommable $consommable,
        Request $request,
        EntityManagerInterface $em,
        EmployeRepository $employeRepo
    ): Response {
        $quantite = (int)$request->get('quantite');
        $description = $request->get('description');
        $employeId = $request->get('employe_id');
        $employe = $employeRepo->find($employeId);

        if (!$employe) {
            $this->addFlash('error', 'Employé invalide.');
            return $this->redirectToRoute('app_admin_consumables');
        }

        if ($quantite > 0 && $quantite <= $consommable->getQuantity()) {
            $consommable->setQuantity($consommable->getQuantity() - $quantite);

            $variation = new Stock();
            $variation->setConsommable($consommable);
            $variation->setQuantiteUtilisee($quantite);
            $variation->setType('Retrait');
            $variation->setDescription($description);
            $variation->setDatePrise(new \DateTime());
            $variation->setEmployee($employe);

            $em->persist($variation);
            $em->flush();
        } else {
            $this->addFlash('error', 'Quantité invalide.');
        }

        return $this->redirectToRoute('app_admin_consumables');
    }

    #[Route('/consommables/{id}/add-stock', name: 'consommable_add_stock', methods: ['POST'])]
    public function addStock(
        Consommable $consommable,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $quantite = (int)$request->get('quantite');
        $description = $request->get('description');

        if ($quantite > 0) {
            $consommable->setQuantity($consommable->getQuantity() + $quantite);

            $stock = new Stock();
            $stock->setConsommable($consommable);
            $stock->setQuantiteUtilisee($quantite);
            $stock->setType('Ajout');
            $stock->setDescription($description);
            $stock->setDatePrise(new \DateTime());
            $stock->setEmployee(null);

            $em->persist($stock);
            $em->flush();
        } else {
            $this->addFlash('error', 'Quantité invalide.');
        }

        return $this->redirectToRoute('app_admin_consumables');
    }

    #[Route('/consommables/{id}/delete', name: 'consommable_delete', methods: ['POST'])]
    public function delete(Consommable $consommable, Request $request, EntityManagerInterface $em): Response
    {
        $submittedToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete' . $consommable->getId(), $submittedToken)) {
            $em->remove($consommable);
            $em->flush();
        }

        return $this->redirectToRoute('consommable_list');
    }

    #[Route('/api/stocks', name: 'api_stocks', methods: ['GET'])]
    public function fetchStocks(Request $request, StockRepository $stockRepo): JsonResponse
    {
        $startDate = $request->query->get('start');
        $endDate = $request->query->get('end');

        $stocks = $stockRepo->createQueryBuilder('s')
            ->where('s.datePrise BETWEEN :start AND :end')
            ->setParameter('start', (new \DateTime($startDate))->setTime(0, 0))
            ->setParameter('end', (new \DateTime($endDate))->setTime(23, 59))
            ->orderBy('s.datePrise', 'DESC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($stocks as $stock) {
            $data[] = [
                'consommable' => $stock->getConsommable()->getNom(),
                'quantiteUtilisee' => $stock->getQuantiteUtilisee(),
                'date' => $stock->getDatePrise()->format('Y-m-d'),
                'employe' => $stock->getEmployee() ? $stock->getEmployee()->getNom() : 'N/A',
                'type' => $stock->getType(),
                'description' => $stock->getDescription(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/consommables', name: 'api_consommables', methods: ['GET'])]
    public function fetchConsommables(ConsommableRepository $consRepo): JsonResponse
    {
        $consommables = $consRepo->findAll();
        $data = array_map(function ($consommable) {
            return [
                'id' => $consommable->getId(),
                'nom' => $consommable->getNom(),
                'quantity' => $consommable->getQuantity(),
                'fournisseur' => $consommable->getFournisseur(),
            ];
        }, $consommables);

        return new JsonResponse($data);
    }
}

