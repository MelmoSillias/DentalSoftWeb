<?php

namespace App\CareDelivery\Application\UseCase;

use App\CareDelivery\Application\Handler\GetOrdonnanceHandler;
use App\CareDelivery\Application\Query\GetOrdonnance;
use App\CareDelivery\Domain\Entity\Consultation;
use App\CareDelivery\Domain\Entity\Ordonnance;
use App\CareDelivery\Domain\Entity\OrdonnanceLigne;
use Doctrine\ORM\EntityManagerInterface;

final class SaveOrdonnance
{
    public function __construct(
        private EntityManagerInterface $em,
        private GetOrdonnanceHandler $getOrdonnance,
    ) {
    }

    public function create(Consultation $consultation, array $payload): array
    {
        $ordonnance = $this->stageCreation($consultation, $payload);
        if (!$ordonnance) {
            return ['error' => 'Aucune ligne fournie'];
        }

        $this->em->flush();

        return ['success' => true, 'id' => $ordonnance->getId()];
    }

    // Consultation updates own the flush when a prescription is embedded in their payload.
    public function stageCreation(Consultation $consultation, array $payload): ?Ordonnance
    {
        $lines = $payload['lignes'] ?? [];
        if (!is_array($lines) || empty($lines)) {
            return null;
        }

        $ordonnance = new Ordonnance();
        $ordonnance->setConsultation($consultation)
            ->setDate(isset($payload['date']) ? new \DateTime($payload['date']) : new \DateTime())
            ->setMedecinNom($payload['medecinNom'] ?? null)
            ->setNote($payload['note'] ?? null);

        foreach ($lines as $line) {
            $this->addLine($ordonnance, $line);
        }

        $this->em->persist($ordonnance);

        return $ordonnance;
    }

    public function update(int $id, array $payload): ?array
    {
        $ordonnance = $this->em->getRepository(Ordonnance::class)->find($id);
        if (!$ordonnance) {
            return null;
        }

        $lines = $payload['lignes'] ?? [];
        if (!is_array($lines) || empty($lines)) {
            throw new \InvalidArgumentException('Au moins une ligne de prescription est requise.');
        }

        if (isset($payload['date']) && is_string($payload['date']) && $payload['date'] !== '') {
            $ordonnance->setDate(new \DateTime($payload['date']));
        }

        if (array_key_exists('medecinNom', $payload)) {
            $ordonnance->setMedecinNom($payload['medecinNom']);
        }

        if (array_key_exists('note', $payload)) {
            $ordonnance->setNote($payload['note']);
        }

        foreach ($ordonnance->getLignes()->toArray() as $line) {
            $ordonnance->removeLigne($line);
            $this->em->remove($line);
        }

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $this->addLine($ordonnance, $line);
        }

        $this->em->flush();

        return ($this->getOrdonnance)(new GetOrdonnance($id));
    }

    private function addLine(Ordonnance $ordonnance, mixed $line): void
    {
        $entity = new OrdonnanceLigne();
        $entity->setOrdonnance($ordonnance)
            ->setDesignation($line['designation'] ?? '')
            ->setPosologie($line['posologie'] ?? null)
            ->setFrequence($line['frequence'] ?? null)
            ->setDuree($line['duree'] ?? null)
            ->setQuantite(isset($line['quantite']) ? (int) $line['quantite'] : null)
            ->setInstructions($line['instructions'] ?? null);
        $this->em->persist($entity);
        $ordonnance->addLigne($entity);
    }
}
