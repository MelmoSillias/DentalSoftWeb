<?php

namespace App\Billing\Application\Query\GetFacturePreview;

final class GetFacturePreviewQuery
{
    public const VARIANT_DETAIL = 'detail';
    public const VARIANT_PRINT = 'print';

    public function __construct(
        public readonly int $factureId,
        public readonly string $variant = self::VARIANT_DETAIL,
    ) {
    }
}
