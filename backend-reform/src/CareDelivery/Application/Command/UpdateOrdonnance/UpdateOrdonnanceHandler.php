<?php

namespace App\CareDelivery\Application\Command\UpdateOrdonnance;

use App\CareDelivery\Application\Port\ConsultationWritePort;
use App\CareDelivery\Domain\Exception\CareDeliveryDomainException;
use App\CareDelivery\Domain\Model\Ordonnance;
use App\CareDelivery\Domain\Model\OrdonnanceLigne;
use App\Shared\Application\Bus\CommandHandler;
use InvalidArgumentException;

final class UpdateOrdonnanceHandler implements CommandHandler
{
    public function __construct(private readonly ConsultationWritePort $writePort)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(UpdateOrdonnanceCommand $command): ?array
    {
        $lignesPayload = $command->payload['lignes'] ?? null;
        if (!is_array($lignesPayload) || $lignesPayload === []) {
            throw new InvalidArgumentException('Au moins une ligne de prescription est requise.');
        }

        try {
            $lignes = [];
            foreach ($lignesPayload as $line) {
                if (!is_array($line)) {
                    continue;
                }

                $lignes[] = new OrdonnanceLigne(
                    (string) ($line['designation'] ?? ''),
                    isset($line['posologie']) ? (string) $line['posologie'] : null,
                    isset($line['frequence']) ? (string) $line['frequence'] : null,
                    isset($line['duree']) ? (string) $line['duree'] : null,
                    isset($line['quantite']) ? (int) $line['quantite'] : null,
                    isset($line['instructions']) ? (string) $line['instructions'] : null,
                );
            }

            // Domain invariant check (≥1 valid line) before legacy persist.
            Ordonnance::create(
                (int) ($command->payload['consultationId'] ?? 1),
                $lignes,
            );
        } catch (CareDeliveryDomainException $e) {
            throw new InvalidArgumentException($e->getMessage(), 0, $e);
        }

        return $this->writePort->updateOrdonnance($command->ordonnanceId, $command->payload);
    }
}
