<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Salle;
use App\Repository\SalleRepository;
use App\Form\SalleType;

final class SalleController extends AbstractController
{
    #[Route('/admin/salles', name: 'app_admin_salles')]
    public function listSalles(SalleRepository $salleRepository): Response
    {
        $addForm = $this->createForm(SalleType::class, new Salle());
        $editForm = $this->createForm(SalleType::class, new Salle());

        return $this->render('admin/salles.html.twig', [
            'salles' => $salleRepository->findAllOrdered(),
            'addForm' => $addForm->createView(),
            'editForm' => $editForm->createView(),
            'active_page' => 'salles'
        ]);
    }

    #[Route('/admin/salles/add', name: 'app_admin_salle_add', methods: ['POST'])]
    public function addSalle(Request $request, EntityManagerInterface $entityManager): Response
    {
        $salle = new Salle();
        $salle->setNom($request->request->get('nom'));
        $salle->setDescription($request->request->get('description'));
        $entityManager->persist($salle);
        $entityManager->flush();

        return $this->redirectToRoute('app_admin_salles');
    }

    #[Route('/admin/salles/edit', name: 'app_admin_salle_edit', methods: ['POST'])]
    public function editSalle(Request $request, SalleRepository $salleRepository, EntityManagerInterface $entityManager): Response
    {
        $salle = $salleRepository->find($request->request->get('id'));
        if ($salle) {
            $salle->setNom($request->request->get('nom'));
            $salle->setDescription($request->request->get('description'));
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_salles');
    }

    #[Route('/admin/salles/delete/{id}', name: 'app_admin_salle_delete', methods: ['POST'])]
    public function deleteSalle(int $id, SalleRepository $salleRepository, EntityManagerInterface $entityManager): Response
    {
        $salle = $salleRepository->find($id);
        if ($salle) {
            $entityManager->remove($salle);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_salles');
    }
}
