<?php

namespace App\ClinicalRecord\Application\Port;

interface FicheMedicaleReadPort
{
    /**
     * @return array<string, mixed>
     */
    public function getFicheJson(int $ficheId): array;
}
