<?php

namespace App\Controller\Admin;

use App\Service\SalleService;
use App\Entity\Salle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Form\SalleType;

final class SalleController extends AbstractController
{
    public function __construct(private SalleService $salleService)
    {
    }

    #[Route('/admin/salles', name: 'app_admin_salles')]
    public function listSalles(): Response
    {
        $addForm = $this->createForm(SalleType::class, new Salle());
        $editForm = $this->createForm(SalleType::class, new Salle());

        return $this->render('admin/salles.html.twig', [
            'salles' => $this->salleService->list(),
            'addForm' => $addForm->createView(),
            'editForm' => $editForm->createView(),
            'active_page' => 'salles'
        ]);
    }

    #[Route('/admin/salles/add', name: 'app_admin_salle_add', methods: ['POST'])]
    public function addSalle(Request $request): Response
    {
        $this->salleService->add([
            'nom' => $request->request->get('nom'),
            'description' => $request->request->get('description'),
        ]);

        return $this->redirectToRoute('app_admin_salles');
    }

    #[Route('/admin/salles/edit', name: 'app_admin_salle_edit', methods: ['POST'])]
    public function editSalle(Request $request): Response
    {
        $this->salleService->edit([
            'id' => $request->request->get('id'),
            'nom' => $request->request->get('nom'),
            'description' => $request->request->get('description'),
        ]);

        return $this->redirectToRoute('app_admin_salles');
    }

    #[Route('/admin/salles/delete/{id}', name: 'app_admin_salle_delete', methods: ['POST'])]
    public function deleteSalle(int $id): Response
    {
        $this->salleService->delete($id);

        return $this->redirectToRoute('app_admin_salles');
    }
}
