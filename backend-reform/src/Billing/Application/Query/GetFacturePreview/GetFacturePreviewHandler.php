<?php

namespace App\Billing\Application\Query\GetFacturePreview;

use App\Billing\Application\Port\ClassicBillingPort;
use App\Shared\Application\Bus\QueryHandler;
use InvalidArgumentException;

final class GetFacturePreviewHandler implements QueryHandler
{
    public function __construct(private readonly ClassicBillingPort $classicBilling)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(GetFacturePreviewQuery $query): ?array
    {
        return match ($query->variant) {
            GetFacturePreviewQuery::VARIANT_DETAIL => $this->classicBilling->previewFactureDetail($query->factureId),
            GetFacturePreviewQuery::VARIANT_PRINT => $this->classicBilling->previewFacture($query->factureId),
            default => throw new InvalidArgumentException(sprintf(
                'Unsupported facture preview variant "%s".',
                $query->variant,
            )),
        };
    }
}
