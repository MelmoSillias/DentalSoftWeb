<?php

namespace App\Controller\Medecin;

use App\Entity\Conge;
use App\Repository\CongeRepository;
use App\Repository\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
 
final class CongeController extends AbstractController
{
    #[Route('/api/conges', name: 'api_add_conge', methods: ['POST'])]
    public function addConge(
        Request $request,
        EntityManagerInterface $em,
        EmployeRepository $employeRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['employeId'], $data['type'], $data['startDate'], $data['endDate'])) {
            return $this->json(['error' => 'Champs manquants.'], 400);
        }

        $employe = $employeRepo->find($data['employeId']);
        if (!$employe) {
            return $this->json(['error' => 'Employé introuvable.'], 404);
        }

        $start = \DateTime::createFromFormat('Y-m-d', $data['startDate']);
        $end = \DateTime::createFromFormat('Y-m-d', $data['endDate']);

        if (!$start || !$end || $end < $start) {
            return $this->json(['error' => 'Dates invalides.'], 400);
        }

        $conge = new Conge();
        $conge->setEmploye($employe);
        $conge->setType($data['type']);
        $conge->setStartDate($start);
        $conge->setEndDate($end);
        $em->persist($conge);
        $em->flush();

        return $this->json([
            'message' => 'Congé ajouté avec succès.',
            'conge' => [
                'id' => $conge->getId(),
                'type' => $conge->getType(),
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'employe' => $employe->getPrenom() . ' ' . $employe->getNom(),
            ]
        ], 201);
    }

    #[Route('/api/conges/all', name: 'api_all_conges', methods: ['GET'])]
    public function getAllConges(CongeRepository $repo): JsonResponse
    {
        $conges = $repo->findAll();

        $data = array_map(function ($conge) {
            return [
                'id' => $conge->getId(),
                'employeId' => $conge->getEmploye()->getId(),
                'type' => $conge->getType(),
                'start' => $conge->getStartDate()->format('Y-m-d'),
                'end' => $conge->getEndDate()->format('Y-m-d')
            ];
        }, $conges);

        return $this->json($data);
    }

    #[Route('/api/employes/conges', name: 'api_employes_conges', methods: ['GET'])]
    public function getEmployesWithConges(EmployeRepository $repo): JsonResponse
    {
        $employes = $repo->findAll();

        $data = array_map(function ($emp) {
            return [
                'id' => $emp->getId(),
                'nom' => $emp->getNom(),
                'prenom' => $emp->getPrenom(),
                'conges' => array_map(function ($c) {
                    return [
                        'id' => $c->getId(),
                        'type' => $c->getType(),
                        'start' => $c->getStartDate()->format('Y-m-d'),
                        'end' => $c->getEndDate()->format('Y-m-d')
                    ];
                }, $emp->getConges()->toArray())
            ];
        }, $employes);

        return $this->json($data);
    }

    
}
