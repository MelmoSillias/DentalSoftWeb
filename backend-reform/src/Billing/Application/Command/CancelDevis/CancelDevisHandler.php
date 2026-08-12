<?php

namespace App\Billing\Application\Command\CancelDevis;

use App\Billing\Domain\Exception\DevisNotFoundException;
use App\Billing\Domain\Repository\DevisRepository;
use App\Billing\Domain\ValueObject\DevisId;
use App\Shared\Application\Bus\CommandHandler;

final class CancelDevisHandler implements CommandHandler
{
    public function __construct(private readonly DevisRepository $devisRepository)
    {
    }

    public function __invoke(CancelDevisCommand $command): void
    {
        $devis = $this->devisRepository->findById(DevisId::fromInt($command->devisId));
        if ($devis === null) {
            throw DevisNotFoundException::withId($command->devisId);
        }

        $devis->cancel();
        $this->devisRepository->save($devis);
    }
}
