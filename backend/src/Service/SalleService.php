<?php

namespace App\Service;

use App\Entity\Salle;
use App\Repository\SalleRepository;
use Doctrine\ORM\EntityManagerInterface;

class SalleService
{
    public function __construct(
        private SalleRepository $salleRepo,
        private EntityManagerInterface $em,
    ) {
    }

    public function list(): array
    {
        return $this->salleRepo->findAllOrdered();
    }

    public function add(array $data): Salle
    {
        $salle = new Salle();
        $salle->setNom($data['nom'] ?? null);
        $salle->setDescription($data['description'] ?? null);
        $this->em->persist($salle);
        $this->em->flush();
        return $salle;  
    }

    public function edit(array $data): ?Salle
    {
        $salle = $this->salleRepo->find($data['id'] ?? null);
        if (!$salle) {
            return null;
        }

        $salle->setNom($data['nom'] ?? $salle->getNom());
        $salle->setDescription($data['description'] ?? $salle->getDescription());
        $this->em->flush();
        return $salle;
    }

    public function delete(int $id): void
    {
        $salle = $this->salleRepo->find($id);
        if ($salle) {
            $this->em->remove($salle);
            $this->em->flush();
        }
    }
}
