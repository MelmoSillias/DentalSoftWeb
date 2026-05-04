<?php

namespace App\Scheduling\Service;

use App\Scheduling\Entity\Conge;
use App\IdentityAccess\Entity\User;
use App\Shared\Event\EntityActionEvent;
use App\Scheduling\Repository\CongeRepository;
use App\IdentityAccess\Repository\EmployeRepository;
use App\Communication\Service\NotificationRecipientResolver;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CongeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CongeRepository $congeRepo,
        private EmployeRepository $employeRepo,
        private \App\Communication\Service\NotificationRecipientResolver $notificationRecipientResolver,
        private EventDispatcherInterface $eventDispatcher,
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
                'employeId' => $employe->getId(),
                'employeNom' => $employe->getNom(),
                'employePrenom' => $employe->getPrenom(),
                'type' => $conge->getType(),
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'employe' => $employe->getPrenom() . ' ' . $employe->getNom(),
            ],
            'status' => 201,
        ];
    }

    public function listConges(array $filters = []): array
    {
        $qb = $this->congeRepo->createQueryBuilder('c')
            ->leftJoin('c.employe', 'e')->addSelect('e')
            ->orderBy('c.startDate', 'DESC')
            ->addOrderBy('c.id', 'DESC');

        if (!empty($filters['employeId'])) {
            $qb->andWhere('e.id = :employeId')
                ->setParameter('employeId', (int) $filters['employeId']);
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('LOWER(c.type) = :type')
                ->setParameter('type', mb_strtolower((string) $filters['type']));
        }

        if (!empty($filters['start'])) {
            $start = $this->createDateFromInput($filters['start']);
            if ($start) {
                $qb->andWhere('c.endDate >= :startDate')
                    ->setParameter('startDate', $start->setTime(0, 0, 0));
            }
        }

        if (!empty($filters['end'])) {
            $end = $this->createDateFromInput($filters['end']);
            if ($end) {
                $qb->andWhere('c.startDate <= :endDate')
                    ->setParameter('endDate', $end->setTime(23, 59, 59));
            }
        }

        $conges = $qb->getQuery()->getResult();

        return array_map(function (Conge $conge) {
            $start = $conge->getStartDate();
            $end = $conge->getEndDate();
            $durationDays = 0;
            if ($start && $end) {
                $durationDays = ((int) $end->diff($start)->format('%a')) + 1;
            }

            return [
                'id' => $conge->getId(),
                'employeId' => $conge->getEmploye()->getId(),
                'employeNom' => $conge->getEmploye()->getNom(),
                'employePrenom' => $conge->getEmploye()->getPrenom(),
                'employe' => trim(($conge->getEmploye()->getPrenom() ?? '') . ' ' . ($conge->getEmploye()->getNom() ?? '')),
                'type' => $conge->getType(),
                'start' => $start?->format('Y-m-d'),
                'end' => $end?->format('Y-m-d'),
                'durationDays' => $durationDays,
            ];
        }, $conges);
    }

    public function updateConge(int $id, array $data, ?User $actor = null): array
    {
        $conge = $this->congeRepo->find($id);
        if (!$conge) {
            return ['error' => 'Congé introuvable.', 'status' => 404];
        }

        if (!isset($data['employeId'], $data['type'], $data['startDate'], $data['endDate'])) {
            return ['error' => 'Champs manquants.', 'status' => 400];
        }

        $employe = $this->employeRepo->find((int) $data['employeId']);
        if (!$employe) {
            return ['error' => 'Employé introuvable.', 'status' => 404];
        }

        $start = $this->createDateFromInput($data['startDate']);
        $end = $this->createDateFromInput($data['endDate']);

        if (!$start || !$end || $end < $start) {
            return ['error' => 'Dates invalides.', 'status' => 400];
        }

        $conge
            ->setEmploye($employe)
            ->setType(trim((string) $data['type']))
            ->setStartDate($start)
            ->setEndDate($end);

        $this->em->flush();
        $this->notifyCongeChange($conge, 'updated', $actor);

        return [
            'message' => 'Congé mis à jour avec succès.',
            'conge' => [
                'id' => $conge->getId(),
                'employeId' => $employe->getId(),
                'employeNom' => $employe->getNom(),
                'employePrenom' => $employe->getPrenom(),
                'type' => $conge->getType(),
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ],
            'status' => 200,
        ];
    }

    public function deleteConge(int $id, ?User $actor = null): array
    {
        $conge = $this->congeRepo->find($id);
        if (!$conge) {
            return ['error' => 'Congé introuvable.', 'status' => 404];
        }

        $this->notifyCongeChange($conge, 'deleted', $actor);
        $this->em->remove($conge);
        $this->em->flush();

        return [
            'message' => 'Congé supprimé avec succès.',
            'status' => 200,
        ];
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
            'updated' => sprintf('Le congé (%s) de %s a été mis à jour: du %s au %s.', strtolower($type), $employeeName, $start, $end),
            'deleted' => sprintf('Le congé (%s) de %s prévu du %s au %s a été supprimé.', strtolower($type), $employeeName, $start, $end),
            default => sprintf('Mise à jour de congé pour %s.', $employeeName),
        };

        $this->eventDispatcher->dispatch(
            new EntityActionEvent(
                $conge,
                $event,
                ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE'],
                $actor,
                [
                    'message' => $message,
                    'priority' => 'info',
                    'type' => 'info',
                    'link' => '/admin/agenda/jours-conges',
                ],
            )
        );
    }

    private function createDateFromInput(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return DateTimeImmutable::createFromFormat('Y-m-d', trim($value)) ?: null;
    }
}
