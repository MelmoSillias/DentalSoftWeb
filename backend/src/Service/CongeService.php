<?php

namespace App\Service;

use App\Entity\Conge;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\CongeRepository;
use App\Repository\EmployeRepository;  
use App\Service\NotificationRecipientResolver;
use App\Service\NotificationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class CongeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CongeRepository $congeRepo,
        private EmployeRepository $employeRepo,
        private NotificationService $notificationService,
        private NotificationRecipientResolver $notificationRecipientResolver,
    ) {
    }

    public function addConge(array $data, ?User $actor = null): array
    {
        if (!isset($data['employeId'], $data['type'], $data['startDate'], $data['endDate'])) {
            return ['error' => 'Champs manquants.', 'status' => 400];
        }

        $employe = $this->employeRepo->find($data['employeId']);
        if (!$employe) {
            return ['error' => 'Employé introuvable.', 'status' => 404];
        }

        $start = DateTimeImmutable::createFromFormat('Y-m-d', $data['startDate']);
        $end = DateTimeImmutable::createFromFormat('Y-m-d', $data['endDate']);

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

        $this->notifyCongeChange($conge, 'created', $actor);

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

    private function notifyCongeChange(Conge $conge, string $event, ?User $actor = null): void
    {
        $recipients = [];

        foreach ($this->notificationRecipientResolver->adminsAndReceptionists($actor) as $user) {
            $recipients[$user->getId() ?? spl_object_id($user)] = $user;
        }

        $employeeUser = $this->notificationRecipientResolver->userForEmploye($conge->getEmploye(), $actor);
        if ($employeeUser) {
            $recipients[$employeeUser->getId() ?? spl_object_id($employeeUser)] = $employeeUser;
        }

        if ($recipients === []) {
            return;
        }

        $employeeName = $conge->getEmploye()?->getFullName() ?? 'Un employé';
        $start = $conge->getStartDate()?->format('d/m/Y') ?? 'date inconnue';
        $end = $conge->getEndDate()?->format('d/m/Y') ?? 'date inconnue';
        $type = $conge->getType() ?? 'congé';

        $message = match ($event) {
            'created' => sprintf('%s sera en congé (%s) du %s au %s.', $employeeName, strtolower($type), $start, $end),
            default => sprintf('Mise à jour de congé pour %s.', $employeeName),
        };

        $this->notificationService->notifyMany(
            array_values($recipients),
            $message,
            Notification::PRIORITY_INFO,
            '/admin/agenda/jours-conges',
            Notification::TYPE_INFO,
            $actor,
        );
    }
}
