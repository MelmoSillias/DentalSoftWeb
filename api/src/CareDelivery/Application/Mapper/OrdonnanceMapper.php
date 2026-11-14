<?php

namespace App\CareDelivery\Application\Mapper;

use App\CareDelivery\Domain\Entity\Consultation;
use App\CareDelivery\Domain\Entity\Ordonnance;
use App\CareDelivery\Domain\Entity\OrdonnanceLigne;

final class OrdonnanceMapper
{
    public function listForConsultation(Consultation $consultation): array
    {
        $rows = [];
        foreach ($consultation->getOrdonnances() as $ordonnance) {
            $rows[] = $this->summary($ordonnance);
        }

        return $rows;
    }

    public function detail(Ordonnance $ordonnance): array
    {
        $summary = $this->summary($ordonnance);

        return [
            'id' => $summary['id'],
            'date' => $summary['date'],
            'medecinNom' => $summary['medecinNom'],
            'note' => $summary['note'],
            'consultationId' => $ordonnance->getConsultation()?->getId(),
            'patient' => $ordonnance->getConsultation()?->getPatient()?->getFullName(),
            'lignes' => $summary['lignes'],
        ];
    }

    private function summary(Ordonnance $ordonnance): array
    {
        return [
            'id' => $ordonnance->getId(),
            'date' => $ordonnance->getDate()->format('Y-m-d'),
            'medecinNom' => $ordonnance->getMedecinNom(),
            'note' => $ordonnance->getNote(),
            'lignes' => array_map(fn (OrdonnanceLigne $line) => [
                'id' => $line->getId(),
                'designation' => $line->getDesignation(),
                'posologie' => $line->getPosologie(),
                'frequence' => $line->getFrequence(),
                'duree' => $line->getDuree(),
                'quantite' => $line->getQuantite(),
                'instructions' => $line->getInstructions(),
            ], $ordonnance->getLignes()->toArray()),
        ];
    }
}
