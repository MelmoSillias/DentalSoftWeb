<?php

namespace App\Service;

use App\Repository\BookingRepository;
use App\Repository\EmployeRepository;
use App\Repository\RdvRepository;
use Doctrine\ORM\EntityManagerInterface;

class AgendaService
{
    public function __construct(
        private EmployeRepository $employeRepo,
        private RdvRepository $rdvRepo,
        private BookingRepository $bookingRepo,
        private EntityManagerInterface $em,
    ) {
    }

    public function getRendezVousContext(?object $user, bool $onlyCurrentMedecin = false, ?string $typeFilter = 'medecin'): array
    {
        $medecins = $onlyCurrentMedecin && $user
            ? [$this->employeRepo->findOneBy(['user' => $user])]
            : ($typeFilter ? $this->employeRepo->findBy(['type' => $typeFilter]) : $this->employeRepo->findAll());

        $medecins = array_values(array_filter($medecins));

        return [
            'medecins' => $medecins,
            'rdvs' => $this->rdvRepo->findAll(),
        ];
    }

    public function deleteBooking(int $id): array
    {
        $booking = $this->bookingRepo->find($id);

        if (!$booking) {
            return ['error' => 'Événement non trouvé', 'status' => 404];
        }

        $this->em->remove($booking);
        $this->em->flush();

        return ['success' => true];
    }

    public function validateBooking(int $id): array
    {
        $booking = $this->bookingRepo->find($id);

        if (!$booking) {
            return ['error' => 'Événement introuvable', 'status' => 404];
        }

        $booking->setStatut(1);
        $this->em->flush();

        return ['success' => true];
    }

    public function listBookings(): array
    {
        $bookings = $this->bookingRepo->findAll();

        return array_map(function ($booking) {
            return [
                'id' => $booking->getId(),
                'title' => $booking->getTitle(),
                'description' => $booking->getDescription(),
                'beginAt' => $booking->getBeginAt()->format('Y-m-d\TH:i:s'),
                'endAt' => $booking->getEndAt()?->format('Y-m-d\TH:i:s'),
                'statut' => $booking->getStatut(),
            ];
        }, $bookings);
    }

    public function createBooking(array $data): array
    {
        if (!isset($data['beginAt'], $data['title'])) {
            return ['error' => 'Données manquantes', 'status' => 400];
        }

        $beginAt = \DateTime::createFromFormat('Y-m-d H:i:s', $data['beginAt']);
        $endAt = isset($data['endAt']) ? \DateTime::createFromFormat('Y-m-d H:i:s', $data['endAt']) : null;

        if (!$beginAt) {
            return ['error' => 'Format de date invalide pour beginAt', 'status' => 400];
        }

        if ($endAt && $endAt < $beginAt) {
            return ['error' => 'endAt ne peut pas être avant beginAt', 'status' => 400];
        }

        $booking = new \App\Entity\Booking();
        $booking->setBeginAt($beginAt)
            ->setEndAt($endAt)
            ->setTitle($data['title'])
            ->setDescription($data['description'] ?? null);

        $this->em->persist($booking);
        $this->em->flush();

        return ['success' => true, 'id' => $booking->getId()];
    }
}
