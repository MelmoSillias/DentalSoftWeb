<?php

namespace App\Billing\Application\Query\GetDevis;

use App\Billing\Application\Port\BillingReadPort;
use App\Billing\Domain\Exception\DevisNotFoundException;
use App\Shared\Application\Bus\QueryHandler;

final class GetDevisHandler implements QueryHandler
{
    public function __construct(private readonly BillingReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetDevisQuery $query): array
    {
        $data = $this->readPort->previewDevis($query->devisId);
        if ($data === null) {
            throw DevisNotFoundException::withId($query->devisId);
        }

        return $data;
    }
}
