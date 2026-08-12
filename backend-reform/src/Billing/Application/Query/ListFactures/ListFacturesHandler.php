<?php

namespace App\Billing\Application\Query\ListFactures;

use App\Billing\Application\Port\BillingReadPort;
use App\Shared\Application\Bus\QueryHandler;
use InvalidArgumentException;

final class ListFacturesHandler implements QueryHandler
{
    public function __construct(private readonly BillingReadPort $readPort)
    {
    }

    /**
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public function __invoke(ListFacturesQuery $query): array
    {
        return match ($query->scope) {
            ListFacturesQuery::SCOPE_ALL => $this->readPort->listAllFactures(
                $this->requireDate($query->start, 'start'),
                $this->requireDate($query->end, 'end'),
            ),
            ListFacturesQuery::SCOPE_CLASSIQUES => $this->readPort->listFacturesClassiques(
                $this->requireDate($query->start, 'start'),
                $this->requireDate($query->end, 'end'),
            ),
            ListFacturesQuery::SCOPE_UNPAID => $this->readPort->listFacturesImpayees($query->start, $query->end),
            default => throw new InvalidArgumentException(sprintf('Unsupported factures list scope "%s".', $query->scope)),
        };
    }

    private function requireDate(?\DateTimeInterface $date, string $name): \DateTimeInterface
    {
        if ($date === null) {
            throw new InvalidArgumentException(sprintf('ListFacturesQuery requires %s for this scope.', $name));
        }

        return $date;
    }
}
