<?php

namespace App\Inventory\Application\Mapper;

use App\Inventory\Domain\Entity\Consommable;
use App\Inventory\Domain\Entity\Stock;

final class ConsommableResponseMapper
{
    public function details(Consommable $consommable): array
    {
        return [
            'id' => $consommable->getId(),
            'nom' => $consommable->getNom(),
            'quantity' => $consommable->getQuantity(),
            'fournisseur' => $consommable->getFournisseur(),
            'lowValue' => $consommable->getLowValue(),
        ];
    }

    public function summary(Consommable $consommable): array
    {
        return $this->details($consommable) + [
            'onlowvalue' => $consommable->getQuantity() < $consommable->getLowValue(),
        ];
    }

    public function stock(Stock $stock): array
    {
        $employeeName = 'N/A';
        if ($stock->getEmployee()) {
            $employeeName = $stock->getEmployee()->getNom();
        } elseif ($stock->getUser()) {
            $employeeName = $stock->getUser()->getUsername();
        }

        return [
            'consommable' => $stock->getConsommable()->getNom(),
            'quantiteUtilisee' => $stock->getQuantiteUtilisee(),
            'date' => $stock->getDatePrise()->format('Y-m-d'),
            'employe' => $employeeName,
            'type' => $stock->getType(),
            'description' => $stock->getDescription(),
        ];
    }
}
