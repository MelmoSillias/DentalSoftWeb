<?php

namespace App\Billing\Application\Policy;

class TransactionTypePolicy
{
    public function resolveTransactionTypeKey(?string $type): string
    {
        $value = strtolower(trim((string) $type));
        $value = str_replace(['é', 'Ã¨', 'Ãª', 'Ã«', 'Ã ', 'Ã¢', 'Ã®', 'Ã¯', 'Ã´', 'Ã¹', 'Ã»', 'Ã§', ' '], ['e', 'e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'u', 'u', 'c', '_'], $value);

        return match (true) {
            in_array($value, ['entry', 'income', 'revenue', 'revenu', 'entree', "Revenue"], true) => 'revenue',
            in_array($value, ['exit', 'expense', 'depense', 'sortie', "Depense"], true) => 'expense',
            default => 'other',
        };
    }

    public function resolveTransactionTypeAliases(string $typeKey): array
    {
        return match ($typeKey) {
            'expense' => ['Depense'],
            default => ['Revenue'],
        };
    }

    public function normalizePersistedTransactionType(string $type): string
    {
        return $this->resolveTransactionTypeKey($type) === 'expense' ? 'Depense' : 'Revenue';
    }
}
