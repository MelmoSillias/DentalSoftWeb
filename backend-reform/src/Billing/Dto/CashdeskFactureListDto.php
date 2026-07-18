<?php

namespace App\Billing\Dto;

final class CashdeskFactureListDto
{
    /** @var list<array<string, mixed>> */
    private array $all;

    public function __construct(
        array $factures,
        array $facturesAssurance,
    ) {
        $merged = array_merge($factures, $facturesAssurance);

        usort($merged, static function (array $a, array $b): int {
            $dateA = $a['date'] ?? '';
            $dateB = $b['date'] ?? '';

            return $dateB <=> $dateA;
        });

        $this->all = $merged;
    }

    /** @return list<array<string, mixed>> */
    public function getAll(): array
    {
        return $this->all;
    }

    public function toArray(): array
    {
        return [
            'all' => $this->all,
        ];
    }
}
