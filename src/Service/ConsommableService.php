<?php

namespace App\Service;

use App\Entity\Consommable;
use App\Entity\Employe;
use App\Entity\Stock;
use App\Repository\ConsommableRepository;
use App\Repository\EmployeRepository;
use App\Repository\StockRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ConsommableService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConsommableRepository $consRepo,
        private StockRepository $stockRepo,
        private EmployeRepository $employeRepo,
    ) {
    }

    public function listConsumablesWithVariations(): array
    {
        return [
            'consommables' => $this->consRepo->findAll(),
            'variations' => $this->stockRepo->findBy([], ['datePrise' => 'DESC']),
        ];
    }

    public function addConsommable(array $data, ?UserInterface $user): array
    {
        $required = ['nom', 'quantite', 'fournisseur', 'lowValue'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return ['error' => "Champ $field manquant", 'status' => 400];
            }
        }

        $c = new Consommable();
        $c->setNom($data['nom']);
        $c->setQuantity((int) ($data['quantite'] ?? 0));
        $c->setFournisseur($data['fournisseur']);
        $c->setLowValue((int) $data['lowValue']);

        $stock = new Stock();
        $stock->setConsommable($c);
        $stock->setQuantiteUtilisee($c->getQuantity());
        $stock->setType('Ajout');
        $stock->setDescription("Ajout d'un nouveau consommable");
        $stock->setDatePrise(new DateTime());

        $employee = $this->employeeFromUser($user);
        if ($employee) {
            $stock->setEmployee($employee);
        }

        $this->em->persist($c);
        $this->em->persist($stock);
        $this->em->flush();

        return ['message' => 'Consommable added successfully', 'status' => 201];
    }

    public function editConsommable(Consommable $consommable, array $data): array
    {
        $consommable->setNom($data['nom'] ?? $consommable->getNom());
        if (isset($data['lowValue'])) {
            $consommable->setLowValue((int) $data['lowValue']);
        }
        if (isset($data['fournisseur'])) {
            $consommable->setFournisseur($data['fournisseur']);
        }
        $this->em->flush();

        return ['message' => 'Consommable updated successfully'];
    }

    public function retrait(Consommable $consommable, array $data): array
    {
        $quantite = (int) ($data['quantite'] ?? 0);
        $description = $data['description'] ?? null;
        $employeId = $data['employe'] ?? null;
        $employe = $employeId ? $this->employeRepo->find($employeId) : null;

        if (!$employe) {
            return ['error' => 'Employé invalide.', 'status' => 400];
        }

        if ($quantite <= 0 || $quantite > $consommable->getQuantity()) {
            return ['error' => 'Quantité invalide.', 'status' => 400];
        }

        $consommable->setQuantity($consommable->getQuantity() - $quantite);
        $variation = new Stock();
        $variation->setConsommable($consommable);
        $variation->setQuantiteUtilisee($quantite);
        $variation->setType('Retrait');
        $variation->setDescription($description);
        $variation->setDatePrise(new DateTime());
        $variation->setEmployee($employe);
        $this->em->persist($variation);
        $this->em->flush();

        return ['message' => 'Stock retired successfully'];
    }

    public function getConsommableDetails(Consommable $consommable): array
    {
        return [
            'id' => $consommable->getId(),
            'nom' => $consommable->getNom(),
            'quantity' => $consommable->getQuantity(),
            'fournisseur' => $consommable->getFournisseur(),
            'lowValue' => $consommable->getLowValue(),
        ];
    }

    public function addStock(Consommable $consommable, array $data, ?UserInterface $user): array
    {
        $quantite = (int) ($data['quantite'] ?? 0);
        $description = $data['description'] ?? null;

        if ($quantite <= 0) {
            return ['error' => 'Quantité invalide.', 'status' => 400];
        }

        $consommable->setQuantity($consommable->getQuantity() + $quantite);
        $stock = new Stock();
        $stock->setConsommable($consommable);
        $stock->setQuantiteUtilisee($quantite);
        $stock->setType('Ajout');
        $stock->setDescription($description);
        $stock->setDatePrise(new DateTime());
        $employee = $this->employeeFromUser($user);
        if ($employee) {
            $stock->setEmployee($employee);
        }
        $this->em->persist($stock);
        $this->em->flush();

        return ['message' => 'Stock added successfully'];
    }

    public function deleteConsommable(Consommable $consommable): array
    {
        $this->em->remove($consommable);
        $this->em->flush();

        return ['message' => 'Consommable deleted successfully'];
    }

    public function fetchStocks(?string $startDate, ?string $endDate): array
    {
        $start = (new DateTime($startDate ?? 'today'))->setTime(0, 0);
        $end = (new DateTime($endDate ?? 'today'))->setTime(23, 59);

        $stocks = $this->stockRepo->createQueryBuilder('s')
            ->where('s.datePrise BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
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

        return $data;
    }

    public function fetchConsommables(): array
    {
        $consommables = $this->consRepo->findAll();

        return array_map(function (Consommable $consommable) {
            return [
                'id' => $consommable->getId(),
                'nom' => $consommable->getNom(),
                'quantity' => $consommable->getQuantity(),
                'fournisseur' => $consommable->getFournisseur(),
                'onlowvalue' => $consommable->getQuantity() < $consommable->getLowValue(),
            ];
        }, $consommables);
    }

    private function employeeFromUser(?UserInterface $user): ?Employe
    {
        if (!$user) {
            return null;
        }

        return $this->employeRepo->findOneBy(['user' => $user]);
    }
}
