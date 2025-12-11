<?php

namespace App\Service;

use App\Entity\Conge;
use App\Repository\CongeRepository;
use App\Repository\EmployeRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class CongeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CongeRepository $congeRepo,
        private EmployeRepository $employeRepo,
    ) {
    }

    public function addConge(array $data): array
    {
        if (!isset($data['employeId'], $data['type'], $data['startDate'], $data['endDate'])) {
            return ['error' => 'Champs manquants.', 'status' => 400];
        }

        $employe = $this->employeRepo->find($data['employeId']);
        if (!$employe) {
            return ['error' => 'Employé introuvable.', 'status' => 404];
        }

        $start = DateTime::createFromFormat('Y-m-d', $data['startDate']);
        $end = DateTime::createFromFormat('Y-m-d', $data['endDate']);

        if (!$start || !$end || $end < $start) {
            return ['error' => 'Dates invalides.', 'status' => 400];
        }

        $conge = new Conge();
        $conge->setEmploye($employe)
            ->setType($data['type'])
            ->setStartDate($start)
            ->setEndDate($end);

        $this->em->persist($conge);
        $this->em->flush();

        return [
            'message' => 'Congé ajouté avec succès.',
            'conge' => [
                'id' => $conge->getId(),
                'type' => $conge->getType(),
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'employe' => $employe->getPrenom() . ' ' . $employe->getNom(),
            ],
            'status' => 201,
        ];
    }

    public function listConges(): array
    {
        $conges = $this->congeRepo->findAll();

        return array_map(function (Conge $conge) {
            return [
                'id' => $conge->getId(),
                'employeId' => $conge->getEmploye()->getId(),
                'type' => $conge->getType(),
                'start' => $conge->getStartDate()->format('Y-m-d'),
                'end' => $conge->getEndDate()->format('Y-m-d'),
            ];
        }, $conges);
    }

    public function listEmployesWithConges(): array
    {
        $employes = $this->employeRepo->findAll();

        return array_map(function ($emp) {
            return [
                'id' => $emp->getId(),
                'nom' => $emp->getNom(),
                'prenom' => $emp->getPrenom(),
                'conges' => array_map(function (Conge $c) {
                    return [
                        'id' => $c->getId(),
                        'type' => $c->getType(),
                        'start' => $c->getStartDate()->format('Y-m-d'),
                        'end' => $c->getEndDate()->format('Y-m-d'),
                    ];
                }, $emp->getConges()->toArray()),
            ];
        }, $employes);
    }
}
