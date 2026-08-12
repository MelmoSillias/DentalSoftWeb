<?php

namespace App\Billing\Application\Command\ValidateDevis;

use App\Billing\Domain\Exception\DevisNotFoundException;
use App\Billing\Domain\Repository\DevisRepository;
use App\Billing\Domain\ValueObject\DevisId;
use App\Shared\Application\Bus\CommandHandler;

final class ValidateDevisHandler implements CommandHandler
{
    public function __construct(private readonly DevisRepository $devisRepository)
    {
    }

    public function __invoke(ValidateDevisCommand $command): void
    {
        $devis = $this->devisRepository->findById(DevisId::fromInt($command->devisId));
        if ($devis === null) {
            throw DevisNotFoundException::withId($command->devisId);
        }

        $devis->validate();
        $this->devisRepository->save($devis);
    }
}
